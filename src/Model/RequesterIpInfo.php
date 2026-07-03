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
 * Enriches {@see IpInfo} with parsed User-Agent data. It is returned by
 * IpregistryClient::lookupOrigin(), where the User-Agent of the calling
 * client is known.
 */
final readonly class RequesterIpInfo extends IpInfo
{
    public function __construct(
        string $ip = '',
        IpType $type = IpType::Unknown,
        string $hostname = '',
        Carrier $carrier = new Carrier(),
        Company $company = new Company(),
        Connection $connection = new Connection(),
        Currency $currency = new Currency(),
        Location $location = new Location(),
        Security $security = new Security(),
        TimeZone $timeZone = new TimeZone(),
        /** The parsed User-Agent of the requester, or null when the API did not return any. */
        public ?UserAgent $userAgent = null,
    ) {
        parent::__construct($ip, $type, $hostname, $carrier, $company, $connection, $currency, $location, $security, $timeZone);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $userAgent = null;
        if (\is_array($data['user_agent'] ?? null)) {
            $userAgent = UserAgent::fromArray($data['user_agent']);
        }

        return new self(...[
            ...self::constructorArgs($data),
            'userAgent' => $userAgent,
        ]);
    }
}
