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

use Ipregistry\Enum\ErrorCode;
use Ipregistry\Enum\IpType;
use Ipregistry\Exception\ApiException;
use Ipregistry\IpregistryClient;
use Ipregistry\Tests\Support\BuiltInServer;
use PHPUnit\Framework\TestCase;

/**
 * Full-stack offline tests: the real client and the real cURL transport
 * against a local fake of the Ipregistry API, mirroring the reference
 * implementation's httptest-based behavior tests.
 */
final class ClientEndToEndTest extends TestCase
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

    private function client(): IpregistryClient
    {
        return new IpregistryClient(apiKey: 'test-key', baseUrl: self::$server->baseUrl);
    }

    public function testLookup(): void
    {
        $info = $this->client()->lookup('8.8.8.8');

        self::assertSame('8.8.8.8', $info->ip);
        self::assertSame(IpType::IPv4, $info->type);
        self::assertSame('US', $info->location->country->code);
        self::assertSame(15169, $info->connection->asn);
    }

    public function testLookupIpv6(): void
    {
        $info = $this->client()->lookup('2001:db8::1');

        self::assertSame('2001:db8::1', $info->ip);
        self::assertSame(IpType::IPv6, $info->type);
    }

    public function testLookupWithHostname(): void
    {
        $info = $this->client()->lookup('8.8.8.8', hostname: true);

        self::assertSame('dns.google', $info->hostname);
    }

    public function testLookupInvalidIpRaisesApiException(): void
    {
        try {
            $this->client()->lookup('invalid');
            self::fail('expected an ApiException');
        } catch (ApiException $e) {
            self::assertSame(ErrorCode::InvalidIpAddress, $e->errorCode);
            self::assertSame(400, $e->status);
        }
    }

    public function testLookupOrigin(): void
    {
        $origin = $this->client()->lookupOrigin();

        self::assertSame('93.184.216.34', $origin->ip);
        self::assertNotNull($origin->userAgent);
        self::assertSame('IpregistryClient/PHP/'.IpregistryClient::VERSION, $origin->userAgent->header);
    }

    public function testLookupBatchMixesSuccessesAndFailures(): void
    {
        $list = $this->client()->lookupBatch(['8.8.8.8', 'not-an-ip', '2001:db8::1']);

        self::assertCount(3, $list);
        self::assertSame('8.8.8.8', $list->at(0)->ip);
        self::assertSame('2001:db8::1', $list->at(2)->ip);

        $error = $list->results[1]->error;
        self::assertNotNull($error);
        self::assertSame(ErrorCode::InvalidIpAddress, $error->errorCode);
    }

    public function testParseUserAgents(): void
    {
        $list = $this->client()->parseUserAgents('Mozilla/5.0 Chrome/120.0');

        self::assertCount(1, $list);
        self::assertSame('Chrome', $list->at(0)->name);
        self::assertSame('Mozilla/5.0 Chrome/120.0', $list->at(0)->header);
    }
}
