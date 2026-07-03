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

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Ipregistry\Exception\ClientException;
use Ipregistry\Http\Psr18Transport;
use Ipregistry\IpregistryClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class Psr18TransportTest extends TestCase
{
    private static function transport(ClientInterface $client): Psr18Transport
    {
        $factory = new HttpFactory();

        return new Psr18Transport($client, $factory, $factory);
    }

    public function testMapsRequestAndResponse(): void
    {
        $psr18 = new class implements ClientInterface {
            public ?RequestInterface $lastRequest = null;

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->lastRequest = $request;

                return new Response(429, ['Retry-After' => '3'], '{"code":"TOO_MANY_REQUESTS"}');
            }
        };

        $response = self::transport($psr18)->request(
            'POST',
            'https://api.ipregistry.co/?hostname=true',
            ['Authorization' => 'ApiKey secret', 'Content-Type' => 'application/json'],
            '["8.8.8.8"]',
            15.0,
        );

        self::assertSame(429, $response->statusCode);
        self::assertSame('3', $response->header('Retry-After'));
        self::assertSame('{"code":"TOO_MANY_REQUESTS"}', $response->body);

        $request = $psr18->lastRequest;
        self::assertNotNull($request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://api.ipregistry.co/?hostname=true', (string) $request->getUri());
        self::assertSame('ApiKey secret', $request->getHeaderLine('Authorization'));
        self::assertSame('["8.8.8.8"]', (string) $request->getBody());
    }

    public function testWrapsPsr18ExceptionsInClientException(): void
    {
        $psr18 = new class implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw new class('connection reset') extends \RuntimeException implements NetworkExceptionInterface {
                    public function getRequest(): RequestInterface
                    {
                        return new \GuzzleHttp\Psr7\Request('GET', 'https://api.ipregistry.co/');
                    }
                };
            }
        };

        try {
            self::transport($psr18)->request('GET', 'https://api.ipregistry.co/8.8.8.8', [], null, 15.0);
            self::fail('expected a ClientException');
        } catch (ClientException $e) {
            self::assertStringContainsString('connection reset', $e->getMessage());
            self::assertInstanceOf(NetworkExceptionInterface::class, $e->getPrevious());
        }
    }

    public function testWorksAsClientTransport(): void
    {
        $psr18 = new class implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return new Response(200, [], '{"ip":"8.8.8.8","type":"IPv4"}');
            }
        };

        $client = new IpregistryClient(apiKey: 'test-key', transport: self::transport($psr18));

        self::assertSame('8.8.8.8', $client->lookup('8.8.8.8')->ip);
    }
}
