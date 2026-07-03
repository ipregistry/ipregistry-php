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

namespace Ipregistry\Enum;

/**
 * The version of an IP address.
 */
enum IpType: string
{
    case IPv4 = 'IPv4';
    case IPv6 = 'IPv6';
    case Unknown = 'Unknown';

    /**
     * Maps a raw API value to its typed form, falling back to Unknown for
     * unrecognized input.
     */
    public static function fromRaw(string $raw): self
    {
        return self::tryFrom($raw) ?? self::Unknown;
    }
}
