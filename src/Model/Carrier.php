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
 * Mobile carrier information associated with an IP address.
 */
final readonly class Carrier
{
    public function __construct(
        public string $name = '',
        /** The Mobile Country Code. */
        public string $mcc = '',
        /** The Mobile Network Code. */
        public string $mnc = '',
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: Hydrator::str($data, 'name'),
            mcc: Hydrator::str($data, 'mcc'),
            mnc: Hydrator::str($data, 'mnc'),
        );
    }
}
