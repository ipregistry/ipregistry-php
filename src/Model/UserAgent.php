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
 * Structured data parsed from a raw User-Agent string.
 */
final readonly class UserAgent
{
    public function __construct(
        /** The raw User-Agent string that was parsed. */
        public string $header = '',
        public string $name = '',
        public string $type = '',
        public string $version = '',
        public string $versionMajor = '',
        public UserAgentDevice $device = new UserAgentDevice(),
        public UserAgentEngine $engine = new UserAgentEngine(),
        public UserAgentOperatingSystem $operatingSystem = new UserAgentOperatingSystem(),
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            header: Hydrator::str($data, 'header'),
            name: Hydrator::str($data, 'name'),
            type: Hydrator::str($data, 'type'),
            version: Hydrator::str($data, 'version'),
            versionMajor: Hydrator::str($data, 'version_major'),
            device: UserAgentDevice::fromArray(Hydrator::arr($data, 'device')),
            engine: UserAgentEngine::fromArray(Hydrator::arr($data, 'engine')),
            operatingSystem: UserAgentOperatingSystem::fromArray(Hydrator::arr($data, 'os')),
        );
    }
}
