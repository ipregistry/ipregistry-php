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
 * Threat-intelligence flags for an IP address.
 */
final readonly class Security
{
    public function __construct(
        public bool $isAbuser = false,
        public bool $isAttacker = false,
        public bool $isBogon = false,
        public bool $isCloudProvider = false,
        public bool $isProxy = false,
        public bool $isRelay = false,
        public bool $isTor = false,
        public bool $isTorExit = false,
        public bool $isAnonymous = false,
        public bool $isThreat = false,
        public bool $isVpn = false,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            isAbuser: Hydrator::bool($data, 'is_abuser'),
            isAttacker: Hydrator::bool($data, 'is_attacker'),
            isBogon: Hydrator::bool($data, 'is_bogon'),
            isCloudProvider: Hydrator::bool($data, 'is_cloud_provider'),
            isProxy: Hydrator::bool($data, 'is_proxy'),
            isRelay: Hydrator::bool($data, 'is_relay'),
            isTor: Hydrator::bool($data, 'is_tor'),
            isTorExit: Hydrator::bool($data, 'is_tor_exit'),
            isAnonymous: Hydrator::bool($data, 'is_anonymous'),
            isThreat: Hydrator::bool($data, 'is_threat'),
            isVpn: Hydrator::bool($data, 'is_vpn'),
        );
    }
}
