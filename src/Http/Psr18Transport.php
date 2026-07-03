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

namespace Ipregistry\Http;

use Ipregistry\Exception\ClientException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Transport that routes requests through any PSR-18 HTTP client (Guzzle,
 * Symfony HttpClient, ...), giving full control over connection pooling,
 * proxying, TLS, and instrumentation.
 *
 * Requires the psr/http-client and psr/http-factory packages, plus an
 * implementation of both (install any PSR-18 client to get them).
 *
 * The per-request timeout passed by the client is ignored: configure
 * timeouts on the underlying PSR-18 client instead.
 */
final class Psr18Transport implements TransportInterface
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    public function request(string $method, string $url, array $headers, ?string $body, float $timeout): TransportResponse
    {
        $request = $this->requestFactory->createRequest($method, $url);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        if (null !== $body) {
            $request = $request->withBody($this->streamFactory->createStream($body));
        }

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new ClientException('request failed: '.$e->getMessage(), $e);
        }

        $responseHeaders = [];
        foreach (array_keys($response->getHeaders()) as $name) {
            $responseHeaders[strtolower((string) $name)] = $response->getHeaderLine((string) $name);
        }

        return new TransportResponse($response->getStatusCode(), $responseHeaders, (string) $response->getBody());
    }
}
