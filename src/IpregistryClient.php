<?php

declare(strict_types=1);

/*
 * Copyright 2019 Ipregistry (https://ipregistry.co).
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Ipregistry;

use Ipregistry\Exception\ApiException;
use Ipregistry\Exception\ClientException;
use Ipregistry\Http\CurlTransport;
use Ipregistry\Http\TransportInterface;
use Ipregistry\Http\TransportResponse;
use Ipregistry\Model\IpInfo;
use Ipregistry\Model\IpInfoList;
use Ipregistry\Model\IpInfoResult;
use Ipregistry\Model\RequesterIpInfo;
use Ipregistry\Model\UserAgentList;
use Psr\SimpleCache\CacheInterface;

/**
 * Sends requests to the Ipregistry API. You can obtain an API key, along
 * with a generous free tier, at https://ipregistry.co.
 *
 * By default the client sends requests through the cURL extension with a
 * 15-second timeout, retries transient failures up to three times, and
 * performs no caching. Behavior is customized with named constructor
 * arguments:
 *
 *     $client = new IpregistryClient(
 *         apiKey: 'YOUR_API_KEY',
 *         cache: new InMemoryCache(),
 *         retryOnTooManyRequests: true,
 *     );
 */
final class IpregistryClient
{
    /** The released version of the client library. */
    public const VERSION = '1.0.0';

    /** The base URL of the Ipregistry API used unless overridden. */
    public const DEFAULT_BASE_URL = 'https://api.ipregistry.co';

    /**
     * The maximum number of IP addresses Ipregistry accepts in a single
     * batch request. lookupBatch() transparently splits larger arrays into
     * several requests so callers never have to.
     */
    public const DEFAULT_MAX_BATCH_SIZE = 1024;

    private const DEFAULT_TIMEOUT = 15.0;
    private const DEFAULT_MAX_RETRIES = 3;
    private const DEFAULT_RETRY_INTERVAL = 1.0;
    private const DEFAULT_USER_AGENT = 'IpregistryClient/PHP/'.self::VERSION;

    private readonly string $baseUrl;
    private readonly int $maxRetries;
    private readonly float $retryInterval;
    private readonly int $maxBatchSize;
    private readonly string $userAgent;
    private readonly TransportInterface $transport;

    /** @var \Closure(float): void */
    private readonly \Closure $sleeper;

    /**
     * @param string                  $apiKey                 the Ipregistry API key used to authenticate requests
     * @param string                  $baseUrl                overrides the API base URL, mainly useful for testing
     *                                                        or pointing at a private deployment
     * @param float                   $timeout                the per-request timeout in seconds; a value <= 0
     *                                                        disables the client-side timeout
     * @param int                     $maxRetries             the maximum number of automatic retries performed in
     *                                                        addition to the initial attempt; 0 disables retries
     * @param float                   $retryInterval          the base backoff in seconds between retries; successive
     *                                                        retries use an exponentially increasing delay
     *                                                        (interval * 2^attempt)
     * @param bool                    $retryOnServerError     whether 5xx responses are retried (transient network
     *                                                        errors are always retried up to $maxRetries)
     * @param bool                    $retryOnTooManyRequests whether 429 Too Many Requests responses are retried,
     *                                                        honoring the Retry-After header when present.
     *                                                        Ipregistry does not rate limit by default (it is
     *                                                        opt-in per API key), so this defaults to false
     * @param int                     $maxBatchSize           the maximum number of IP addresses sent in a single
     *                                                        batch request; lookupBatch() splits larger arrays
     * @param CacheInterface|null     $cache                  a PSR-16 cache used to memoize successful single and
     *                                                        batch IP lookups; disabled by default so that data is
     *                                                        never stale. Origin lookups are never cached, because
     *                                                        the requester IP is only known from the response
     * @param int|\DateInterval|null  $cacheTtl               the lifetime of cached entries; null uses the cache's
     *                                                        default
     * @param string|null             $userAgent              overrides the User-Agent header sent with requests
     * @param TransportInterface|null $transport              the HTTP transport; defaults to {@see CurlTransport}.
     *                                                        Use {@see Http\Psr18Transport} to route requests
     *                                                        through any PSR-18 client
     * @param \Closure|null           $sleeper                overridable sleep function taking a delay in seconds,
     *                                                        for testing retry backoff without waiting
     */
    public function __construct(
        #[\SensitiveParameter]
        private readonly string $apiKey,
        string $baseUrl = self::DEFAULT_BASE_URL,
        private readonly float $timeout = self::DEFAULT_TIMEOUT,
        int $maxRetries = self::DEFAULT_MAX_RETRIES,
        float $retryInterval = self::DEFAULT_RETRY_INTERVAL,
        private readonly bool $retryOnServerError = true,
        private readonly bool $retryOnTooManyRequests = false,
        int $maxBatchSize = self::DEFAULT_MAX_BATCH_SIZE,
        private readonly ?CacheInterface $cache = null,
        private readonly int|\DateInterval|null $cacheTtl = null,
        ?string $userAgent = null,
        ?TransportInterface $transport = null,
        ?\Closure $sleeper = null,
    ) {
        if ('' === $apiKey) {
            throw new ClientException('apiKey must not be empty');
        }
        if ($maxBatchSize < 1 || $maxBatchSize > self::DEFAULT_MAX_BATCH_SIZE) {
            throw new ClientException(\sprintf('maxBatchSize must be between 1 and %d', self::DEFAULT_MAX_BATCH_SIZE));
        }

        $this->baseUrl = rtrim($baseUrl, '/');
        $this->maxRetries = max(0, $maxRetries);
        $this->retryInterval = $retryInterval > 0 ? $retryInterval : self::DEFAULT_RETRY_INTERVAL;
        $this->maxBatchSize = $maxBatchSize;
        $this->userAgent = null !== $userAgent && '' !== $userAgent ? $userAgent : self::DEFAULT_USER_AGENT;
        $this->transport = $transport ?? new CurlTransport();
        $this->sleeper = $sleeper ?? static function (float $seconds): void {
            usleep((int) round($seconds * 1_000_000));
        };
    }

    /**
     * Returns the data associated with the given IP address. The $ip
     * argument must be a non-empty IPv4 or IPv6 address; to look up the
     * requester's own IP, use {@see lookupOrigin()} instead.
     *
     * When a cache is configured, a hit is returned without contacting the
     * API.
     *
     * @param string|null           $fields   restricts the response to the given fields, using
     *                                        Ipregistry's field selector syntax (for example
     *                                        "location.country.name,security"), reducing payload
     *                                        size. See https://ipregistry.co/docs/filtering-selecting-fields
     * @param bool|null             $hostname enables reverse-DNS hostname resolution for the
     *                                        looked-up IP address (disabled by default)
     * @param array<string, scalar> $params   arbitrary extra query parameters not covered by a
     *                                        dedicated argument
     *
     * @throws ApiException    when the API reports a failure
     * @throws ClientException on network errors or an undecodable response
     */
    public function lookup(string $ip, ?string $fields = null, ?bool $hostname = null, array $params = []): IpInfo
    {
        if ('' === $ip) {
            throw new ClientException('ip must not be empty; use lookupOrigin() for the requester IP address');
        }

        $query = self::buildQuery($fields, $hostname, $params);
        $cacheKey = self::cacheKey($ip, $query);
        $cached = $this->cacheGet($cacheKey);
        if (null !== $cached) {
            return $cached;
        }

        $data = $this->requestJson('GET', $this->url('/'.rawurlencode($ip), $query), null);
        $info = IpInfo::fromArray($data);

        $this->cacheSet($cacheKey, $info);

        return $info;
    }

    /**
     * Returns the data associated with the IP address the request
     * originates from, enriched with parsed User-Agent data. Origin lookups
     * are never cached, because the requester IP is only known from the
     * response.
     *
     * @param string|null           $fields   restricts the response to the given fields
     * @param bool|null             $hostname enables reverse-DNS hostname resolution
     * @param array<string, scalar> $params   arbitrary extra query parameters
     *
     * @throws ApiException    when the API reports a failure
     * @throws ClientException on network errors or an undecodable response
     */
    public function lookupOrigin(?string $fields = null, ?bool $hostname = null, array $params = []): RequesterIpInfo
    {
        $query = self::buildQuery($fields, $hostname, $params);
        $data = $this->requestJson('GET', $this->url('/', $query), null);

        return RequesterIpInfo::fromArray($data);
    }

    /**
     * Resolves several IP addresses at once. The returned IpInfoList
     * preserves the order of $ips, and each entry may independently succeed
     * or fail. A thrown exception indicates the whole request failed (for
     * example authentication or a network error), not the failure of an
     * individual entry.
     *
     * Arrays larger than the API's per-request limit (1024 addresses) are
     * transparently split into several sequential requests. Entries already
     * present in the cache are served locally; only the remainder are
     * requested from the API, and freshly resolved entries are cached.
     *
     * @param list<string>          $ips      the IPv4 or IPv6 addresses to resolve
     * @param string|null           $fields   restricts the response to the given fields
     * @param bool|null             $hostname enables reverse-DNS hostname resolution
     * @param array<string, scalar> $params   arbitrary extra query parameters
     *
     * @throws ApiException    when the API reports a whole-request failure
     * @throws ClientException on invalid input, network errors, or an undecodable response
     */
    public function lookupBatch(array $ips, ?string $fields = null, ?bool $hostname = null, array $params = []): IpInfoList
    {
        foreach ($ips as $index => $ip) {
            if (!\is_string($ip) || '' === $ip) {
                throw new ClientException(\sprintf('ips[%d] must be a non-empty string', $index));
            }
        }
        /** @var list<string> $ips */
        $ips = array_values($ips);

        $query = self::buildQuery($fields, $hostname, $params);

        /** @var array<int, IpInfo> $cached */
        $cached = [];
        /** @var list<string> $misses */
        $misses = [];
        foreach ($ips as $index => $ip) {
            $info = $this->cacheGet(self::cacheKey($ip, $query));
            if (null !== $info) {
                $cached[$index] = $info;
            } else {
                $misses[] = $ip;
            }
        }

        $fresh = $this->resolveMisses($misses, $query);

        $results = [];
        $next = 0;
        foreach ($ips as $index => $ip) {
            if (isset($cached[$index])) {
                $results[] = IpInfoResult::success($cached[$index]);
                continue;
            }
            if (!isset($fresh[$next])) {
                // Defensive: the API returned fewer results than requested.
                $results[] = IpInfoResult::failure(new ApiException('missing result for requested IP address'));
                continue;
            }
            $result = $fresh[$next];
            ++$next;
            $results[] = $result;
            if (null !== $result->info) {
                $this->cacheSet(self::cacheKey($ip, $query), $result->info);
            }
        }

        return new IpInfoList($results);
    }

    /**
     * Parses one or more raw User-Agent strings (such as the User-Agent
     * header of an incoming HTTP request) into structured data. Results
     * preserve the order of the input.
     *
     * @throws ApiException    when the API reports a whole-request failure
     * @throws ClientException on network errors or an undecodable response
     */
    public function parseUserAgents(string ...$userAgents): UserAgentList
    {
        $data = $this->requestJson('POST', $this->baseUrl.'/user_agent', self::encodeJson(array_values($userAgents)));

        return UserAgentList::fromArray($data);
    }

    /**
     * Fetches fresh data for the cache-missed IP addresses, splitting them
     * into API-sized chunks dispatched sequentially. The returned results
     * preserve the order of $misses.
     *
     * @param list<string> $misses
     *
     * @return list<IpInfoResult>
     */
    private function resolveMisses(array $misses, string $query): array
    {
        $results = [];
        foreach (array_chunk($misses, $this->maxBatchSize) as $chunk) {
            $data = $this->requestJson('POST', $this->url('/', $query), self::encodeJson($chunk));
            foreach (IpInfoList::fromArray($data)->results as $result) {
                $results[] = $result;
            }
        }

        return $results;
    }

    /**
     * Performs an HTTP request with automatic retries and decodes the JSON
     * body of a 200 response. Non-2xx responses are converted to
     * ApiException; transport failures to ClientException.
     *
     * @return array<array-key, mixed>
     */
    private function requestJson(string $method, string $url, ?string $body): array
    {
        $headers = [
            'Authorization' => 'ApiKey '.$this->apiKey,
            'User-Agent' => $this->userAgent,
            'Accept' => 'application/json',
        ];
        if (null !== $body) {
            $headers['Content-Type'] = 'application/json';
        }

        for ($attempt = 0;; ++$attempt) {
            try {
                $response = $this->transport->request($method, $url, $headers, $body, $this->timeout);
            } catch (ClientException $e) {
                // Transport errors are retried up to maxRetries regardless of
                // the retry-on-status flags, matching the reference implementation.
                if ($attempt < $this->maxRetries) {
                    $this->backoff($attempt, 0.0);
                    continue;
                }

                throw $e;
            }

            if (200 === $response->statusCode) {
                return self::decodeJson($response->body);
            }

            if ($this->shouldRetryStatus($response->statusCode) && $attempt < $this->maxRetries) {
                $this->backoff($attempt, self::parseRetryAfter($response->header('Retry-After')));
                continue;
            }

            throw self::parseApiError($response);
        }
    }

    /**
     * Reports whether a non-2xx status is eligible for retry given the
     * client's configuration.
     */
    private function shouldRetryStatus(int $status): bool
    {
        if (429 === $status) {
            return $this->retryOnTooManyRequests;
        }

        return $status >= 500 && $status < 600 && $this->retryOnServerError;
    }

    /**
     * Waits before the next retry attempt, honoring an explicit Retry-After
     * duration when positive and otherwise using exponential backoff.
     */
    private function backoff(int $attempt, float $retryAfter): void
    {
        $delay = $retryAfter > 0 ? $retryAfter : $this->retryInterval * (2 ** min($attempt, 30));
        ($this->sleeper)($delay);
    }

    /**
     * Converts a non-2xx response into an ApiException, falling back to a
     * generic message when the body is not a recognizable error payload.
     */
    private static function parseApiError(TransportResponse $response): ApiException
    {
        $payload = json_decode($response->body, true);
        if (\is_array($payload) && \is_string($payload['code'] ?? null) && '' !== $payload['code']) {
            return ApiException::fromPayload($payload, $response->statusCode);
        }

        return new ApiException('unexpected HTTP status '.$response->statusCode, status: $response->statusCode);
    }

    /**
     * Parses a Retry-After header expressed as an integer number of seconds.
     * Returns 0 when the header is absent or not a valid non-negative
     * integer (the HTTP-date form is not supported, matching the reference
     * client).
     */
    private static function parseRetryAfter(?string $value): float
    {
        if (null === $value || 1 !== preg_match('/^\d+$/', trim($value))) {
            return 0.0;
        }

        return (float) trim($value);
    }

    /**
     * Collapses lookup arguments into a canonical query string. Keys are
     * sorted so that equivalent lookups produce identical cache keys.
     *
     * @param array<string, scalar> $params
     */
    private static function buildQuery(?string $fields, ?bool $hostname, array $params): string
    {
        $query = [];
        foreach ($params as $name => $value) {
            $query[$name] = \is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }
        if (null !== $fields) {
            $query['fields'] = $fields;
        }
        if (null !== $hostname) {
            $query['hostname'] = $hostname ? 'true' : 'false';
        }
        ksort($query);

        return http_build_query($query, '', '&', \PHP_QUERY_RFC3986);
    }

    private function url(string $path, string $query): string
    {
        $url = $this->baseUrl.$path;
        if ('' !== $query) {
            $url .= '?'.$query;
        }

        return $url;
    }

    /**
     * Derives a deterministic, PSR-16-safe cache key from an IP address and
     * its canonical query string.
     */
    private static function cacheKey(string $ip, string $query): string
    {
        return 'ipregistry_'.hash('xxh128', $ip.'?'.$query);
    }

    private function cacheGet(string $key): ?IpInfo
    {
        if (null === $this->cache) {
            return null;
        }
        $value = $this->cache->get($key);

        return $value instanceof IpInfo ? $value : null;
    }

    private function cacheSet(string $key, IpInfo $info): void
    {
        $this->cache?->set($key, $info, $this->cacheTtl);
    }

    /**
     * @param array<array-key, mixed> $value
     */
    private static function encodeJson(array $value): string
    {
        try {
            return json_encode($value, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ClientException('failed to encode request body', $e);
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function decodeJson(string $body): array
    {
        try {
            $data = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ClientException('failed to decode response', $e);
        }
        if (!\is_array($data)) {
            throw new ClientException('failed to decode response: expected a JSON object');
        }

        return $data;
    }
}
