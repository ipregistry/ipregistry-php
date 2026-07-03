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

use Ipregistry\Exception\ClientException;
use Ipregistry\Http\CurlTransport;
use Ipregistry\Tests\Support\BuiltInServer;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the real cURL transport against PHP's built-in server.
 */
final class CurlTransportTest extends TestCase
{
    private static BuiltInServer $server;

    public static function setUpBeforeClass(): void
    {
        self::$server = BuiltInServer::start(__DIR__.'/../Fixtures/api-server.php');
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
    }

    public function testGetRequestCarriesHeadersAndQuery(): void
    {
        $transport = new CurlTransport();

        $response = $transport->request(
            'GET',
            self::$server->baseUrl.'/echo?alpha=1&beta=two',
            ['Authorization' => 'ApiKey secret', 'Accept' => 'application/json'],
            null,
            10.0,
        );

        self::assertSame(200, $response->statusCode);

        $echo = json_decode($response->body, true);
        self::assertIsArray($echo);
        self::assertSame('GET', $echo['method']);
        self::assertSame(['alpha' => '1', 'beta' => 'two'], $echo['query']);
        $headers = $echo['headers'];
        self::assertIsArray($headers);
        self::assertSame('ApiKey secret', $headers['authorization']);
        self::assertSame('', $echo['body']);
    }

    public function testPostRequestCarriesBody(): void
    {
        $transport = new CurlTransport();

        $response = $transport->request(
            'POST',
            self::$server->baseUrl.'/echo',
            ['Content-Type' => 'application/json'],
            '["8.8.8.8"]',
            10.0,
        );

        $echo = json_decode($response->body, true);
        self::assertIsArray($echo);
        self::assertSame('POST', $echo['method']);
        self::assertSame('["8.8.8.8"]', $echo['body']);
        $headers = $echo['headers'];
        self::assertIsArray($headers);
        self::assertSame('application/json', $headers['content-type']);
    }

    public function testNonSuccessStatusAndHeadersAreReported(): void
    {
        $transport = new CurlTransport();

        $response = $transport->request('GET', self::$server->baseUrl.'/status/503', [], null, 10.0);

        self::assertSame(503, $response->statusCode);
        self::assertSame('present', $response->header('X-Test-Header'));
        self::assertSame('present', $response->header('x-test-header'));
        self::assertNull($response->header('X-Absent'));
    }

    public function testTimeoutRaisesClientException(): void
    {
        $transport = new CurlTransport();

        $this->expectException(ClientException::class);

        $transport->request('GET', self::$server->baseUrl.'/slow', [], null, 0.2);
    }

    public function testConnectionErrorRaisesClientException(): void
    {
        $transport = new CurlTransport(connectTimeout: 1.0);

        $this->expectException(ClientException::class);

        // TEST-NET-1 (RFC 5737) is guaranteed unroutable.
        $transport->request('GET', 'http://192.0.2.1:81/', [], null, 1.0);
    }

    public function testTransportIsReusableAcrossRequests(): void
    {
        $transport = new CurlTransport();

        $first = $transport->request('GET', self::$server->baseUrl.'/status/200', [], null, 10.0);
        $second = $transport->request('GET', self::$server->baseUrl.'/status/404', [], null, 10.0);

        self::assertSame(200, $first->statusCode);
        self::assertSame(404, $second->statusCode);
    }
}
