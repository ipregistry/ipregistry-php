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

namespace Ipregistry\Tests\System;

use Ipregistry\Enum\ErrorCode;
use Ipregistry\Exception\ApiException;
use Ipregistry\IpregistryClient;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * System tests run against the live Ipregistry API. They are excluded from
 * the default test run (they belong to the "system" group) and skip unless
 * the IPREGISTRY_API_KEY environment variable is set:
 *
 *     IPREGISTRY_API_KEY=YOUR_API_KEY vendor/bin/phpunit --group system
 *
 * A valid API key is required; each successful lookup consumes credits.
 */
#[Group('system')]
final class SystemTest extends TestCase
{
    private function client(): IpregistryClient
    {
        $apiKey = getenv('IPREGISTRY_API_KEY');
        if (false === $apiKey || '' === $apiKey) {
            self::markTestSkipped('set IPREGISTRY_API_KEY to run system tests');
        }

        return new IpregistryClient(apiKey: $apiKey, timeout: 30.0);
    }

    public function testLookup(): void
    {
        $info = $this->client()->lookup('8.8.8.8');

        self::assertSame('8.8.8.8', $info->ip);
        self::assertNotSame('', $info->location->country->code);
        self::assertNotNull($info->connection->asn, 'expected a non-null ASN for a well-known address');
    }

    public function testLookupOrigin(): void
    {
        $origin = $this->client()->lookupOrigin();

        self::assertNotSame('', $origin->ip);
    }

    public function testLookupBatch(): void
    {
        $ips = ['8.8.8.8', '1.1.1.1', 'not-an-ip'];
        $list = $this->client()->lookupBatch($ips);

        self::assertCount(3, $list);
        self::assertSame('8.8.8.8', $list->at(0)->ip);

        // The invalid address should surface as a per-entry error.
        $error = $list->results[2]->error;
        self::assertNotNull($error, 'entry 2 (invalid IP) should have failed');
    }

    public function testLookupInvalidIp(): void
    {
        try {
            $this->client()->lookup('invalid');
            self::fail('expected an ApiException');
        } catch (ApiException $e) {
            self::assertSame(ErrorCode::InvalidIpAddress, $e->errorCode);
        }
    }

    public function testParseUserAgents(): void
    {
        $list = $this->client()->parseUserAgents(
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
        );

        self::assertCount(1, $list);
        self::assertNotSame('', $list->at(0)->name);
    }
}
