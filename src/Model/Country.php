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
 * Country-level information for a location.
 */
final readonly class Country
{
    /**
     * @param list<string>   $borders   ISO 3166-1 alpha-2 codes of bordering countries
     * @param list<Language> $languages languages spoken in the country
     */
    public function __construct(
        /** The total land area in square kilometers. */
        public float $area = 0.0,
        public array $borders = [],
        public string $callingCode = '',
        public string $capital = '',
        /** The ISO 3166-1 alpha-2 country code (for example "US"). */
        public string $code = '',
        public string $name = '',
        /** The estimated number of inhabitants. */
        public int $population = 0,
        /** The number of inhabitants per square kilometer. */
        public float $populationDensity = 0.0,
        public Flag $flag = new Flag(),
        public array $languages = [],
        /** The country-code top-level domain (for example ".us"). */
        public string $tld = '',
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $languages = [];
        foreach (Hydrator::arr($data, 'languages') as $language) {
            if (\is_array($language)) {
                $languages[] = Language::fromArray($language);
            }
        }

        return new self(
            area: Hydrator::float($data, 'area'),
            borders: Hydrator::strList($data, 'borders'),
            callingCode: Hydrator::str($data, 'calling_code'),
            capital: Hydrator::str($data, 'capital'),
            code: Hydrator::str($data, 'code'),
            name: Hydrator::str($data, 'name'),
            population: Hydrator::int($data, 'population'),
            populationDensity: Hydrator::float($data, 'population_density'),
            flag: Flag::fromArray(Hydrator::arr($data, 'flag')),
            languages: $languages,
            tld: Hydrator::str($data, 'tld'),
        );
    }
}
