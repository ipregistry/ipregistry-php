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

namespace Ipregistry\Tests\Unit;

use Ipregistry\Enum\CompanyType;
use Ipregistry\Enum\ConnectionType;
use Ipregistry\Enum\IpType;
use Ipregistry\Exception\ApiException;
use Ipregistry\Model\IpInfo;
use Ipregistry\Model\IpInfoList;
use Ipregistry\Model\RequesterIpInfo;
use Ipregistry\Model\UserAgentList;
use PHPUnit\Framework\TestCase;

final class ModelsTest extends TestCase
{
    public function testIpInfoFromFullPayload(): void
    {
        $info = IpInfo::fromArray([
            'ip' => '8.8.8.8',
            'type' => 'IPv4',
            'hostname' => 'dns.google',
            'carrier' => ['name' => 'AT&T', 'mcc' => '310', 'mnc' => '410'],
            'company' => ['name' => 'Google LLC', 'domain' => 'google.com', 'type' => 'business'],
            'connection' => [
                'asn' => 15169,
                'domain' => 'google.com',
                'organization' => 'Google LLC',
                'route' => '8.8.8.0/24',
                'type' => 'hosting',
            ],
            'currency' => [
                'code' => 'USD',
                'name' => 'US Dollar',
                'symbol' => '$',
                'format' => [
                    'decimal_separator' => '.',
                    'group_separator' => ',',
                    'negative' => ['prefix' => '-$', 'suffix' => ''],
                    'positive' => ['prefix' => '$', 'suffix' => ''],
                ],
            ],
            'location' => [
                'continent' => ['code' => 'NA', 'name' => 'North America'],
                'country' => [
                    'area' => 9629091.0,
                    'borders' => ['CA', 'MX'],
                    'calling_code' => '1',
                    'capital' => 'Washington D.C.',
                    'code' => 'US',
                    'name' => 'United States',
                    'population' => 331002651,
                    'population_density' => 35.61,
                    'flag' => ['emoji' => "\u{1F1FA}\u{1F1F8}"],
                    'languages' => [['code' => 'en', 'name' => 'English', 'native' => 'English']],
                    'tld' => '.us',
                ],
                'region' => ['code' => 'US-CA', 'name' => 'California'],
                'city' => 'Mountain View',
                'postal' => '94043',
                'latitude' => 37.405992,
                'longitude' => -122.078515,
                'language' => ['code' => 'en', 'name' => 'English', 'native' => 'English'],
                'in_eu' => false,
            ],
            'security' => ['is_vpn' => true, 'is_threat' => true],
            'time_zone' => [
                'id' => 'America/Los_Angeles',
                'abbreviation' => 'PST',
                'current_time' => '2026-07-03T01:02:03-08:00',
                'name' => 'Pacific Standard Time',
                'offset' => -28800,
                'in_daylight_saving' => true,
            ],
        ]);

        self::assertSame('8.8.8.8', $info->ip);
        self::assertSame(IpType::IPv4, $info->type);
        self::assertSame('dns.google', $info->hostname);
        self::assertSame('AT&T', $info->carrier->name);
        self::assertSame(CompanyType::Business, $info->company->type);
        self::assertSame(15169, $info->connection->asn);
        self::assertSame(ConnectionType::Hosting, $info->connection->type);
        self::assertSame('USD', $info->currency->code);
        self::assertSame('-$', $info->currency->format->negative->prefix);
        self::assertSame('NA', $info->location->continent->code);
        self::assertSame(['CA', 'MX'], $info->location->country->borders);
        self::assertSame(331002651, $info->location->country->population);
        self::assertSame('en', $info->location->country->languages[0]->code);
        self::assertSame('US-CA', $info->location->region->code);
        self::assertSame(37.405992, $info->location->latitude);
        self::assertFalse($info->location->inEu);
        self::assertTrue($info->security->isVpn);
        self::assertTrue($info->security->isThreat);
        self::assertFalse($info->security->isTor);
        self::assertSame(-28800, $info->timeZone->offset);
        self::assertTrue($info->timeZone->inDaylightSaving);
    }

    public function testIpInfoFromEmptyPayloadIsFullyTraversable(): void
    {
        $info = IpInfo::fromArray([]);

        self::assertSame('', $info->ip);
        self::assertSame(IpType::Unknown, $info->type);
        self::assertNull($info->connection->asn);
        self::assertNull($info->company->type);
        self::assertNull($info->location->latitude);
        self::assertSame('', $info->location->country->flag->emoji);
        self::assertSame([], $info->location->country->languages);
        self::assertFalse($info->security->isVpn);
        self::assertSame(0, $info->timeZone->offset);
    }

    public function testIpInfoToleratesMistypedFields(): void
    {
        $info = IpInfo::fromArray([
            'ip' => ['not' => 'a string'],
            'type' => 'IPvFuture',
            'connection' => ['asn' => 'not-a-number', 'type' => 'quantum'],
            'location' => 'not-an-object',
        ]);

        self::assertSame('', $info->ip);
        self::assertSame(IpType::Unknown, $info->type);
        self::assertNull($info->connection->asn);
        self::assertNull($info->connection->type);
        self::assertSame('', $info->location->country->code);
    }

    public function testRequesterIpInfoParsesUserAgent(): void
    {
        $info = RequesterIpInfo::fromArray([
            'ip' => '93.184.216.34',
            'type' => 'IPv4',
            'user_agent' => [
                'header' => 'Mozilla/5.0',
                'name' => 'Chrome',
                'type' => 'browser',
                'version' => '120.0',
                'version_major' => '120',
                'device' => ['brand' => 'Apple', 'name' => 'Mac', 'type' => 'desktop'],
                'engine' => ['name' => 'Blink', 'type' => 'browser', 'version' => '120'],
                'os' => ['name' => 'macOS', 'type' => 'desktop', 'version' => '10.15.7'],
            ],
        ]);

        self::assertSame('93.184.216.34', $info->ip);
        self::assertNotNull($info->userAgent);
        self::assertSame('Chrome', $info->userAgent->name);
        self::assertSame('Apple', $info->userAgent->device->brand);
        self::assertSame('Blink', $info->userAgent->engine->name);
        self::assertSame('macOS', $info->userAgent->operatingSystem->name);
    }

    public function testRequesterIpInfoWithoutUserAgent(): void
    {
        $info = RequesterIpInfo::fromArray(['ip' => '93.184.216.34']);

        self::assertNull($info->userAgent);
    }

    public function testIpInfoListMapsEntriesToResultsOrErrors(): void
    {
        $list = IpInfoList::fromArray([
            'results' => [
                ['ip' => '8.8.8.8', 'type' => 'IPv4'],
                [
                    'code' => 'INVALID_IP_ADDRESS',
                    'message' => 'Invalid IP address',
                    'resolution' => 'Specify a valid IP address.',
                ],
            ],
        ]);

        self::assertCount(2, $list);
        self::assertSame('8.8.8.8', $list->at(0)->ip);

        $entries = iterator_to_array($list);
        self::assertNotNull($entries[0]->info);
        self::assertNull($entries[0]->error);
        self::assertNull($entries[1]->info);
        self::assertNotNull($entries[1]->error);

        try {
            $list->at(1);
            self::fail('expected an ApiException');
        } catch (ApiException $e) {
            self::assertSame('INVALID_IP_ADDRESS', $e->rawCode);
        }
    }

    public function testIpInfoListAtOutOfRange(): void
    {
        $this->expectException(\OutOfRangeException::class);
        (new IpInfoList())->at(0);
    }

    public function testUserAgentListMapsEntriesToResultsOrErrors(): void
    {
        $list = UserAgentList::fromArray([
            'results' => [
                ['header' => 'Mozilla/5.0', 'name' => 'Chrome'],
                ['code' => 'BAD_REQUEST', 'message' => 'nope', 'resolution' => ''],
            ],
        ]);

        self::assertCount(2, $list);
        self::assertSame('Chrome', $list->at(0)->name);

        $this->expectException(ApiException::class);
        $list->at(1);
    }
}
