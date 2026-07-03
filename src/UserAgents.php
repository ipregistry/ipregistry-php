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

namespace Ipregistry;

/**
 * Helpers around raw User-Agent strings.
 */
final class UserAgents
{
    private function __construct()
    {
    }

    /**
     * Reports whether the given raw User-Agent string looks like a crawler
     * or bot. It is a lightweight heuristic — useful for skipping IP lookups
     * on automated traffic — that matches the substrings "bot", "spider",
     * and "slurp" case-insensitively.
     */
    public static function isBot(string $userAgent): bool
    {
        $userAgent = strtolower($userAgent);

        return str_contains($userAgent, 'bot')
            || str_contains($userAgent, 'spider')
            || str_contains($userAgent, 'slurp');
    }
}
