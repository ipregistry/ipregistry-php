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

/**
 * The geographical location associated with an IP address.
 */
final readonly class Location
{
    public function __construct(
        public Continent $continent = new Continent(),
        public Country $country = new Country(),
        public Region $region = new Region(),
        public string $city = '',
        public string $postal = '',
        /** The decimal-degree latitude, or null when unavailable. */
        public ?float $latitude = null,
        /** The decimal-degree longitude, or null when unavailable. */
        public ?float $longitude = null,
        /** The primary language spoken at the location. */
        public Language $language = new Language(),
        /** Whether the location is within a European Union member state. */
        public bool $inEu = false,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            continent: Continent::fromArray(Hydrator::arr($data, 'continent')),
            country: Country::fromArray(Hydrator::arr($data, 'country')),
            region: Region::fromArray(Hydrator::arr($data, 'region')),
            city: Hydrator::str($data, 'city'),
            postal: Hydrator::str($data, 'postal'),
            latitude: Hydrator::floatOrNull($data, 'latitude'),
            longitude: Hydrator::floatOrNull($data, 'longitude'),
            language: Language::fromArray(Hydrator::arr($data, 'language')),
            inEu: Hydrator::bool($data, 'in_eu'),
        );
    }
}
