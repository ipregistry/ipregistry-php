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

use Ipregistry\Cache\InMemoryCache;
use Ipregistry\Cache\InvalidCacheKeyException;
use PHPUnit\Framework\TestCase;

final class InMemoryCacheTest extends TestCase
{
    public function testSetGetDelete(): void
    {
        $cache = new InMemoryCache();

        self::assertNull($cache->get('missing'));
        self::assertSame('fallback', $cache->get('missing', 'fallback'));
        self::assertFalse($cache->has('key'));

        self::assertTrue($cache->set('key', 'value'));
        self::assertSame('value', $cache->get('key'));
        self::assertTrue($cache->has('key'));

        self::assertTrue($cache->delete('key'));
        self::assertNull($cache->get('key'));
    }

    public function testEntriesExpireAfterTtl(): void
    {
        $now = 1000.0;
        $cache = new InMemoryCache(defaultTtl: 60, clock: static function () use (&$now): float {
            return $now;
        });

        $cache->set('key', 'value');
        self::assertSame('value', $cache->get('key'));

        $now += 59.0;
        self::assertSame('value', $cache->get('key'));

        $now += 2.0;
        self::assertNull($cache->get('key'));
        self::assertSame(0, $cache->size());
    }

    public function testExplicitTtlOverridesDefault(): void
    {
        $now = 1000.0;
        $cache = new InMemoryCache(defaultTtl: 60, clock: static function () use (&$now): float {
            return $now;
        });

        $cache->set('key', 'value', 3600);
        $now += 120.0;
        self::assertSame('value', $cache->get('key'));

        $cache->set('other', 'value', new \DateInterval('PT2S'));
        $now += 10.0;
        self::assertNull($cache->get('other'));
    }

    public function testNonPositiveTtlRemovesTheEntry(): void
    {
        $cache = new InMemoryCache();

        $cache->set('key', 'value');
        $cache->set('key', 'value', 0);

        self::assertNull($cache->get('key'));
    }

    public function testLeastRecentlyUsedEntryIsEvicted(): void
    {
        $cache = new InMemoryCache(maxSize: 2);

        $cache->set('a', 1);
        $cache->set('b', 2);
        self::assertSame(1, $cache->get('a')); // touch "a" so "b" becomes LRU

        $cache->set('c', 3);

        self::assertSame(1, $cache->get('a'));
        self::assertNull($cache->get('b'));
        self::assertSame(3, $cache->get('c'));
        self::assertSame(2, $cache->size());
    }

    public function testUpdatingAKeyRefreshesRecency(): void
    {
        $cache = new InMemoryCache(maxSize: 2);

        $cache->set('a', 1);
        $cache->set('b', 2);
        $cache->set('a', 10); // "b" is now the least recently used entry
        $cache->set('c', 3);

        self::assertSame(10, $cache->get('a'));
        self::assertNull($cache->get('b'));
    }

    public function testClearAndMultiOperations(): void
    {
        $cache = new InMemoryCache();

        self::assertTrue($cache->setMultiple(['a' => 1, 'b' => 2]));
        self::assertSame(['a' => 1, 'b' => 2, 'c' => null], iterator_to_array(self::toIterator($cache->getMultiple(['a', 'b', 'c']))));

        self::assertTrue($cache->deleteMultiple(['a']));
        self::assertNull($cache->get('a'));
        self::assertSame(2, $cache->get('b'));

        self::assertTrue($cache->clear());
        self::assertSame(0, $cache->size());
    }

    public function testRejectsEmptyKey(): void
    {
        $this->expectException(InvalidCacheKeyException::class);
        (new InMemoryCache())->get('');
    }

    public function testRejectsReservedCharactersInKey(): void
    {
        $this->expectException(InvalidCacheKeyException::class);
        (new InMemoryCache())->set('bad:key', 'value');
    }

    public function testRejectsNonPositiveMaxSize(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new InMemoryCache(maxSize: 0);
    }

    /**
     * @param iterable<string, mixed> $values
     *
     * @return \Iterator<string, mixed>
     */
    private static function toIterator(iterable $values): \Iterator
    {
        return \is_array($values) ? new \ArrayIterator($values) : new \IteratorIterator($values);
    }
}
