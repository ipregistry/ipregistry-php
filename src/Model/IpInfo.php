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

namespace Ipregistry\Model;

use Ipregistry\Enum\IpType;

/**
 * The comprehensive set of information associated with an IP address
 * returned by the Ipregistry API.
 *
 * Nested objects (carrier, company, connection, currency, location, security,
 * and timeZone) are always present, so accessing their properties never
 * fails even when the API omitted them; absent fields hold their default
 * value (empty string, false, or null for optional numerics).
 */
readonly class IpInfo
{
    public function __construct(
        /** The IP address the data refers to. */
        public string $ip = '',
        /** The IP version (IPv4, IPv6, or Unknown). */
        public IpType $type = IpType::Unknown,
        /**
         * The reverse-DNS hostname, when hostname resolution is requested
         * (see the $hostname lookup argument) and available.
         */
        public string $hostname = '',
        public Carrier $carrier = new Carrier(),
        public Company $company = new Company(),
        public Connection $connection = new Connection(),
        public Currency $currency = new Currency(),
        public Location $location = new Location(),
        public Security $security = new Security(),
        public TimeZone $timeZone = new TimeZone(),
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(...self::constructorArgs($data));
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array{
     *     ip: string,
     *     type: IpType,
     *     hostname: string,
     *     carrier: Carrier,
     *     company: Company,
     *     connection: Connection,
     *     currency: Currency,
     *     location: Location,
     *     security: Security,
     *     timeZone: TimeZone,
     * }
     */
    protected static function constructorArgs(array $data): array
    {
        return [
            'ip' => Hydrator::str($data, 'ip'),
            'type' => IpType::fromRaw(Hydrator::str($data, 'type')),
            'hostname' => Hydrator::str($data, 'hostname'),
            'carrier' => Carrier::fromArray(Hydrator::arr($data, 'carrier')),
            'company' => Company::fromArray(Hydrator::arr($data, 'company')),
            'connection' => Connection::fromArray(Hydrator::arr($data, 'connection')),
            'currency' => Currency::fromArray(Hydrator::arr($data, 'currency')),
            'location' => Location::fromArray(Hydrator::arr($data, 'location')),
            'security' => Security::fromArray(Hydrator::arr($data, 'security')),
            'timeZone' => TimeZone::fromArray(Hydrator::arr($data, 'time_zone')),
        ];
    }
}
