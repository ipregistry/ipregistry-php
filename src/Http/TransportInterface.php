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

/**
 * Abstracts the HTTP layer used by the client to reach the Ipregistry API.
 *
 * The default implementation is {@see CurlTransport}. Use
 * {@see Psr18Transport} to route requests through any PSR-18 HTTP client, or
 * implement this interface for full control (custom instrumentation, request
 * recording in tests, and so on).
 *
 * Implementations perform a single HTTP exchange and never retry: automatic
 * retries are handled by the IpregistryClient itself.
 */
interface TransportInterface
{
    /**
     * Performs a single HTTP request and returns the response, whatever its
     * status code.
     *
     * @param string                $method  the HTTP method, such as "GET" or "POST"
     * @param string                $url     the absolute request URL
     * @param array<string, string> $headers request headers as a name-to-value map
     * @param string|null           $body    the raw request body, or null to send none
     * @param float                 $timeout the request timeout in seconds; non-positive
     *                                       values mean no client-side timeout
     *
     * @throws ClientException when the request cannot be sent or no response
     *                         is received (DNS failure, connection error, timeout, ...)
     */
    public function request(string $method, string $url, array $headers, ?string $body, float $timeout): TransportResponse;
}
