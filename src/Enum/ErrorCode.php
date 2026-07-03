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
 * Strongly typed Ipregistry API error code. It lets callers branch on error
 * conditions without matching on raw strings. See
 * https://ipregistry.co/docs/errors for the authoritative list.
 */
enum ErrorCode: string
{
    case BadRequest = 'BAD_REQUEST';
    case DisabledApiKey = 'DISABLED_API_KEY';
    case ForbiddenIp = 'FORBIDDEN_IP';
    case ForbiddenOrigin = 'FORBIDDEN_ORIGIN';
    case ForbiddenIpOrigin = 'FORBIDDEN_IP_ORIGIN';
    case Internal = 'INTERNAL';
    case InsufficientCredits = 'INSUFFICIENT_CREDITS';
    case InvalidApiKey = 'INVALID_API_KEY';
    case InvalidAsn = 'INVALID_ASN';
    case InvalidFilterSyntax = 'INVALID_FILTER_SYNTAX';
    case InvalidIpAddress = 'INVALID_IP_ADDRESS';
    case MissingApiKey = 'MISSING_API_KEY';
    case ReservedAsn = 'RESERVED_ASN';
    case ReservedIpAddress = 'RESERVED_IP_ADDRESS';
    case TooManyAsns = 'TOO_MANY_ASNS';
    case TooManyIps = 'TOO_MANY_IPS';
    case TooManyRequests = 'TOO_MANY_REQUESTS';
    case TooManyUserAgents = 'TOO_MANY_USER_AGENTS';
    case UnknownAsn = 'UNKNOWN_ASN';

    /**
     * Maps a raw API error code to its typed form, tolerating surrounding
     * whitespace and lowercase input. Returns null when the raw code is not
     * recognized.
     */
    public static function tryFromRaw(string $raw): ?self
    {
        return self::tryFrom(strtoupper(trim($raw)));
    }
}
