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

namespace Ipregistry\Tests\Unit;

use Ipregistry\UserAgents;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UserAgentsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function userAgents(): iterable
    {
        yield 'googlebot' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', true];
        yield 'uppercase bot' => ['SomeBOT/1.0', true];
        yield 'spider' => ['Baiduspider/2.0', true];
        yield 'slurp' => ['Mozilla/5.0 (compatible; Yahoo! Slurp)', true];
        yield 'chrome' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/120.0 Safari/537.36', false];
        yield 'empty' => ['', false];
    }

    #[DataProvider('userAgents')]
    public function testIsBot(string $userAgent, bool $expected): void
    {
        self::assertSame($expected, UserAgents::isBot($userAgent));
    }
}
