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

use Ipregistry\Enum\ConnectionType;

/**
 * Network connection information for an IP address.
 */
final readonly class Connection
{
    public function __construct(
        /** The Autonomous System Number, or null when unknown. */
        public ?int $asn = null,
        public string $domain = '',
        public string $organization = '',
        public string $route = '',
        /** The connection classification, or null when unknown. */
        public ?ConnectionType $type = null,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            asn: Hydrator::intOrNull($data, 'asn'),
            domain: Hydrator::str($data, 'domain'),
            organization: Hydrator::str($data, 'organization'),
            route: Hydrator::str($data, 'route'),
            type: ConnectionType::tryFrom(Hydrator::str($data, 'type')),
        );
    }
}
