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

namespace Ipregistry\Tests\Behavior;

use Ipregistry\Cache\InMemoryCache;
use Ipregistry\Enum\ErrorCode;
use Ipregistry\Exception\ApiException;
use Ipregistry\Exception\ClientException;
use Ipregistry\IpregistryClient;
use Ipregistry\Model\RequesterIpInfo;
use Ipregistry\Tests\Support\RecordingTransport;
use PHPUnit\Framework\TestCase;

final class IpregistryClientTest extends TestCase
{
    private RecordingTransport $transport;

    /** @var list<float> */
    private array $sleeps = [];

    protected function setUp(): void
    {
        $this->transport = new RecordingTransport();
        $this->sleeps = [];
    }

    /**
     * @param array<string, mixed> $options
     */
    private function client(array $options = []): IpregistryClient
    {
        $arguments = [
            'apiKey' => 'test-key',
            'transport' => $this->transport,
            'sleeper' => function (float $seconds): void {
                $this->sleeps[] = $seconds;
            },
            ...$options,
        ];

        // @phpstan-ignore-next-line named arguments spread from a mixed-valued map
        return new IpregistryClient(...$arguments);
    }

    public function testLookupSendsExpectedRequest(): void
    {
        $this->transport->queueJson(200, ['ip' => '8.8.8.8', 'type' => 'IPv4']);

        $info = $this->client()->lookup('8.8.8.8');

        self::assertSame('8.8.8.8', $info->ip);
        self::assertCount(1, $this->transport->requests);

        $request = $this->transport->requests[0];
        self::assertSame('GET', $request['method']);
        self::assertSame(IpregistryClient::DEFAULT_BASE_URL.'/8.8.8.8', $request['url']);
        self::assertSame('ApiKey test-key', $request['headers']['Authorization']);
        self::assertSame('IpregistryClient/PHP/'.IpregistryClient::VERSION, $request['headers']['User-Agent']);
        self::assertSame('application/json', $request['headers']['Accept']);
        self::assertArrayNotHasKey('Content-Type', $request['headers']);
        self::assertNull($request['body']);
        self::assertSame(15.0, $request['timeout']);
    }

    public function testLookupBuildsSortedQueryParameters(): void
    {
        $this->transport->queueJson(200, ['ip' => '8.8.8.8']);

        $this->client()->lookup('8.8.8.8', fields: 'location.country.name,security', hostname: true, params: ['key2' => 'z']);

        $url = $this->transport->requests[0]['url'];
        self::assertSame(
            IpregistryClient::DEFAULT_BASE_URL.'/8.8.8.8?fields=location.country.name%2Csecurity&hostname=true&key2=z',
            $url,
        );
    }

    public function testLookupEncodesIpv6AddressInPath(): void
    {
        $this->transport->queueJson(200, ['ip' => '2001:db8::1', 'type' => 'IPv6']);

        $this->client()->lookup('2001:db8::1');

        self::assertSame(IpregistryClient::DEFAULT_BASE_URL.'/2001%3Adb8%3A%3A1', $this->transport->requests[0]['url']);
    }

    public function testLookupRejectsEmptyIp(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('lookupOrigin');

        $this->client()->lookup('');
    }

    public function testEmptyApiKeyIsRejected(): void
    {
        $this->expectException(ClientException::class);

        new IpregistryClient('');
    }

    public function testBaseUrlTrailingSlashIsTrimmed(): void
    {
        $this->transport->queueJson(200, ['ip' => '8.8.8.8']);

        $this->client(['baseUrl' => 'https://eu.api.ipregistry.co///'])->lookup('8.8.8.8');

        self::assertSame('https://eu.api.ipregistry.co/8.8.8.8', $this->transport->requests[0]['url']);
    }

    public function testLookupOriginTargetsRootAndParsesUserAgent(): void
    {
        $this->transport->queueJson(200, [
            'ip' => '93.184.216.34',
            'type' => 'IPv4',
            'user_agent' => ['header' => 'curl/8', 'name' => 'curl'],
        ]);

        $origin = $this->client()->lookupOrigin();

        self::assertInstanceOf(RequesterIpInfo::class, $origin);
        self::assertSame('93.184.216.34', $origin->ip);
        self::assertNotNull($origin->userAgent);
        self::assertSame('curl', $origin->userAgent->name);
        self::assertSame(IpregistryClient::DEFAULT_BASE_URL.'/', $this->transport->requests[0]['url']);
    }

    public function testApiErrorIsMappedToApiException(): void
    {
        $this->transport->queueJson(403, [
            'code' => 'DISABLED_API_KEY',
            'message' => 'The API key is disabled.',
            'resolution' => 'Enable the key from the dashboard.',
        ]);

        try {
            $this->client()->lookup('8.8.8.8');
            self::fail('expected an ApiException');
        } catch (ApiException $e) {
            self::assertSame('DISABLED_API_KEY', $e->rawCode);
            self::assertSame(ErrorCode::DisabledApiKey, $e->errorCode);
            self::assertSame('Enable the key from the dashboard.', $e->resolution);
            self::assertSame(403, $e->status);
        }
    }

    public function testUnrecognizedErrorBodyFallsBackToGenericApiException(): void
    {
        $this->transport->queueResponse(418, 'not json at all');

        try {
            $this->client()->lookup('8.8.8.8');
            self::fail('expected an ApiException');
        } catch (ApiException $e) {
            self::assertSame('ipregistry: unexpected HTTP status 418', $e->getMessage());
            self::assertSame(418, $e->status);
            self::assertSame('', $e->rawCode);
        }
    }

    public function testUndecodableSuccessBodyThrowsClientException(): void
    {
        $this->transport->queueResponse(200, '{invalid');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('failed to decode response');

        $this->client()->lookup('8.8.8.8');
    }

    public function testServerErrorsAreRetriedWithExponentialBackoff(): void
    {
        $this->transport->queueJson(500, ['code' => 'INTERNAL', 'message' => 'boom', 'resolution' => '']);
        $this->transport->queueJson(502, ['code' => 'INTERNAL', 'message' => 'boom', 'resolution' => '']);
        $this->transport->queueJson(200, ['ip' => '8.8.8.8']);

        $info = $this->client(['retryInterval' => 0.5])->lookup('8.8.8.8');

        self::assertSame('8.8.8.8', $info->ip);
        self::assertCount(3, $this->transport->requests);
        self::assertSame([0.5, 1.0], $this->sleeps);
    }

    public function testServerErrorsAreNotRetriedWhenDisabled(): void
    {
        $this->transport->queueJson(500, ['code' => 'INTERNAL', 'message' => 'boom', 'resolution' => '']);

        try {
            $this->client(['retryOnServerError' => false])->lookup('8.8.8.8');
            self::fail('expected an ApiException');
        } catch (ApiException $e) {
            self::assertSame(500, $e->status);
        }

        self::assertCount(1, $this->transport->requests);
        self::assertSame([], $this->sleeps);
    }

    public function testRetriesStopAfterMaxRetries(): void
    {
        foreach (range(1, 4) as $ignored) {
            $this->transport->queueJson(503, ['code' => 'INTERNAL', 'message' => 'down', 'resolution' => '']);
        }

        try {
            $this->client()->lookup('8.8.8.8');
            self::fail('expected an ApiException');
        } catch (ApiException $e) {
            self::assertSame(503, $e->status);
        }

        // 1 initial attempt + 3 retries.
        self::assertCount(4, $this->transport->requests);
        self::assertSame([1.0, 2.0, 4.0], $this->sleeps);
    }

    public function testTooManyRequestsIsNotRetriedByDefault(): void
    {
        $this->transport->queueJson(429, ['code' => 'TOO_MANY_REQUESTS', 'message' => 'slow down', 'resolution' => '']);

        try {
            $this->client()->lookup('8.8.8.8');
            self::fail('expected an ApiException');
        } catch (ApiException $e) {
            self::assertSame(ErrorCode::TooManyRequests, $e->errorCode);
        }

        self::assertCount(1, $this->transport->requests);
    }

    public function testTooManyRequestsHonorsRetryAfterWhenEnabled(): void
    {
        $this->transport->queueJson(429, ['code' => 'TOO_MANY_REQUESTS', 'message' => 'slow down', 'resolution' => ''], ['Retry-After' => '7']);
        $this->transport->queueJson(200, ['ip' => '8.8.8.8']);

        $info = $this->client(['retryOnTooManyRequests' => true])->lookup('8.8.8.8');

        self::assertSame('8.8.8.8', $info->ip);
        self::assertSame([7.0], $this->sleeps);
    }

    public function testTransportErrorsAreRetriedThenSurfaced(): void
    {
        $this->transport->queueException();
        $this->transport->queueJson(200, ['ip' => '8.8.8.8']);

        $info = $this->client()->lookup('8.8.8.8');
        self::assertSame('8.8.8.8', $info->ip);

        // With retries disabled the error surfaces immediately.
        $this->transport->queueException(new ClientException('connection refused'));

        try {
            $this->client(['maxRetries' => 0])->lookup('1.1.1.1');
            self::fail('expected a ClientException');
        } catch (ClientException $e) {
            self::assertStringContainsString('connection refused', $e->getMessage());
        }
    }

    public function testLookupUsesTheCache(): void
    {
        $cache = new InMemoryCache();
        $this->transport->queueJson(200, ['ip' => '8.8.8.8', 'type' => 'IPv4']);

        $client = $this->client(['cache' => $cache]);

        $first = $client->lookup('8.8.8.8');
        $second = $client->lookup('8.8.8.8');

        self::assertCount(1, $this->transport->requests);
        self::assertSame($first->ip, $second->ip);
    }

    public function testCacheKeyDependsOnLookupParameters(): void
    {
        $cache = new InMemoryCache();
        $this->transport->queueJson(200, ['ip' => '8.8.8.8']);
        $this->transport->queueJson(200, ['ip' => '8.8.8.8', 'hostname' => 'dns.google']);

        $client = $this->client(['cache' => $cache]);

        $client->lookup('8.8.8.8');
        $withHostname = $client->lookup('8.8.8.8', hostname: true);

        self::assertCount(2, $this->transport->requests);
        self::assertSame('dns.google', $withHostname->hostname);
    }

    public function testLookupBatchPostsAllIpsAndPreservesOrder(): void
    {
        $this->transport->queueJson(200, ['results' => [
            ['ip' => '8.8.8.8', 'type' => 'IPv4'],
            ['code' => 'INVALID_IP_ADDRESS', 'message' => 'Invalid IP address', 'resolution' => 'Fix it.'],
            ['ip' => '1.1.1.1', 'type' => 'IPv4'],
        ]]);

        $list = $this->client()->lookupBatch(['8.8.8.8', 'oops', '1.1.1.1']);

        self::assertCount(3, $list);
        self::assertSame('8.8.8.8', $list->at(0)->ip);
        self::assertSame('1.1.1.1', $list->at(2)->ip);
        self::assertNotNull($list->results[1]->error);
        self::assertSame(ErrorCode::InvalidIpAddress, $list->results[1]->error->errorCode);

        $request = $this->transport->requests[0];
        self::assertSame('POST', $request['method']);
        self::assertSame(IpregistryClient::DEFAULT_BASE_URL.'/', $request['url']);
        self::assertSame('application/json', $request['headers']['Content-Type']);
        self::assertSame('["8.8.8.8","oops","1.1.1.1"]', $request['body']);
    }

    public function testLookupBatchSplitsLargeInputIntoChunks(): void
    {
        $this->transport->queueJson(200, ['results' => [['ip' => '10.0.0.1'], ['ip' => '10.0.0.2']]]);
        $this->transport->queueJson(200, ['results' => [['ip' => '10.0.0.3'], ['ip' => '10.0.0.4']]]);
        $this->transport->queueJson(200, ['results' => [['ip' => '10.0.0.5']]]);

        $ips = ['10.0.0.1', '10.0.0.2', '10.0.0.3', '10.0.0.4', '10.0.0.5'];
        $list = $this->client(['maxBatchSize' => 2])->lookupBatch($ips);

        self::assertCount(3, $this->transport->requests);
        self::assertSame('["10.0.0.1","10.0.0.2"]', $this->transport->requests[0]['body']);
        self::assertSame('["10.0.0.3","10.0.0.4"]', $this->transport->requests[1]['body']);
        self::assertSame('["10.0.0.5"]', $this->transport->requests[2]['body']);

        self::assertCount(5, $list);
        foreach ($ips as $index => $ip) {
            self::assertSame($ip, $list->at($index)->ip);
        }
    }

    public function testLookupBatchServesCachedEntriesLocally(): void
    {
        $cache = new InMemoryCache();
        $client = $this->client(['cache' => $cache]);

        $this->transport->queueJson(200, ['ip' => '8.8.8.8', 'type' => 'IPv4']);
        $client->lookup('8.8.8.8');

        $this->transport->queueJson(200, ['results' => [['ip' => '1.1.1.1', 'type' => 'IPv4']]]);
        $list = $client->lookupBatch(['8.8.8.8', '1.1.1.1']);

        self::assertCount(2, $this->transport->requests);
        self::assertSame('["1.1.1.1"]', $this->transport->requests[1]['body'], 'only the cache miss is requested');
        self::assertSame('8.8.8.8', $list->at(0)->ip);
        self::assertSame('1.1.1.1', $list->at(1)->ip);

        // A subsequent batch is fully served from the cache.
        $list = $client->lookupBatch(['8.8.8.8', '1.1.1.1']);
        self::assertCount(2, $this->transport->requests);
        self::assertSame('1.1.1.1', $list->at(1)->ip);
    }

    public function testLookupBatchWithEmptyInputSkipsTheApi(): void
    {
        $list = $this->client()->lookupBatch([]);

        self::assertCount(0, $list);
        self::assertCount(0, $this->transport->requests);
    }

    public function testLookupBatchRejectsNonStringEntries(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('ips[1]');

        $this->client()->lookupBatch(['8.8.8.8', '']);
    }

    public function testLookupBatchFillsMissingResultsWithErrors(): void
    {
        $this->transport->queueJson(200, ['results' => [['ip' => '8.8.8.8']]]);

        $list = $this->client()->lookupBatch(['8.8.8.8', '1.1.1.1']);

        self::assertCount(2, $list);
        self::assertSame('8.8.8.8', $list->at(0)->ip);
        self::assertNotNull($list->results[1]->error);
        self::assertStringContainsString('missing result', $list->results[1]->error->getMessage());
    }

    public function testParseUserAgents(): void
    {
        $this->transport->queueJson(200, ['results' => [
            ['header' => 'Mozilla/5.0', 'name' => 'Chrome', 'os' => ['name' => 'macOS']],
            ['code' => 'BAD_REQUEST', 'message' => 'unparseable', 'resolution' => ''],
        ]]);

        $list = $this->client()->parseUserAgents('Mozilla/5.0', "\x00garbage");

        self::assertCount(2, $list);
        self::assertSame('Chrome', $list->at(0)->name);
        self::assertSame('macOS', $list->at(0)->operatingSystem->name);
        self::assertNotNull($list->results[1]->error);

        $request = $this->transport->requests[0];
        self::assertSame('POST', $request['method']);
        self::assertSame(IpregistryClient::DEFAULT_BASE_URL.'/user_agent', $request['url']);
    }

    public function testCustomUserAgentAndTimeout(): void
    {
        $this->transport->queueJson(200, ['ip' => '8.8.8.8']);

        $this->client(['userAgent' => 'my-app/2.0', 'timeout' => 3.5])->lookup('8.8.8.8');

        $request = $this->transport->requests[0];
        self::assertSame('my-app/2.0', $request['headers']['User-Agent']);
        self::assertSame(3.5, $request['timeout']);
    }

    public function testMaxBatchSizeIsValidated(): void
    {
        $this->expectException(ClientException::class);

        new IpregistryClient('key', maxBatchSize: IpregistryClient::DEFAULT_MAX_BATCH_SIZE + 1);
    }
}
