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

namespace Ipregistry\Cache;

use Psr\SimpleCache\CacheInterface;

/**
 * A PSR-16 in-process cache with time-based expiration and a bounded size
 * using least-recently-used eviction. Pass an instance to IpregistryClient
 * to memoize IP lookups within the current PHP process.
 *
 * Because a cache instance lives and dies with the PHP process, this
 * implementation mainly benefits long-running processes (queue workers,
 * Swoole/FrankenPHP/RoadRunner servers, CLI tools). In classic per-request
 * PHP-FPM deployments, plug in any shared PSR-16 cache instead (APCu, Redis,
 * Memcached, ...).
 */
final class InMemoryCache implements CacheInterface
{
    public const DEFAULT_MAX_SIZE = 4096;
    public const DEFAULT_TTL = 600;

    /**
     * Entries ordered from least to most recently used (PHP arrays preserve
     * insertion order; a touched entry is re-appended).
     *
     * @var array<string, array{value: mixed, expiresAt: float}>
     */
    private array $entries = [];

    /** @var \Closure(): float */
    private readonly \Closure $clock;

    /**
     * @param int                      $maxSize    maximum number of entries held before the least
     *                                             recently used one is evicted
     * @param int                      $defaultTtl entry lifetime in seconds applied when set() is
     *                                             called without an explicit TTL
     * @param (\Closure(): float)|null $clock      overridable time source returning a Unix
     *                                             timestamp in seconds, for testing
     */
    public function __construct(
        private readonly int $maxSize = self::DEFAULT_MAX_SIZE,
        private readonly int $defaultTtl = self::DEFAULT_TTL,
        ?\Closure $clock = null,
    ) {
        if ($maxSize <= 0) {
            throw new \InvalidArgumentException('maxSize must be positive');
        }
        if ($defaultTtl <= 0) {
            throw new \InvalidArgumentException('defaultTtl must be positive');
        }
        $this->clock = $clock ?? static fn (): float => microtime(true);
    }

    /**
     * Entries expire with time and reads refresh their recency: two reads of a key can differ.
     *
     * @phpstan-impure
     */
    public function get(string $key, mixed $default = null): mixed
    {
        self::validateKey($key);

        if (!\array_key_exists($key, $this->entries)) {
            return $default;
        }

        $entry = $this->entries[$key];
        if (($this->clock)() >= $entry['expiresAt']) {
            unset($this->entries[$key]);

            return $default;
        }

        // Refresh recency: move the entry to the most recently used position.
        unset($this->entries[$key]);
        $this->entries[$key] = $entry;

        return $entry['value'];
    }

    public function set(string $key, mixed $value, int|\DateInterval|null $ttl = null): bool
    {
        self::validateKey($key);

        $seconds = $this->ttlToSeconds($ttl);
        if ($seconds <= 0) {
            // Per PSR-16, a non-positive TTL means the entry is already expired.
            unset($this->entries[$key]);

            return true;
        }

        unset($this->entries[$key]);
        $this->entries[$key] = [
            'value' => $value,
            'expiresAt' => ($this->clock)() + $seconds,
        ];

        if (\count($this->entries) > $this->maxSize) {
            $oldest = array_key_first($this->entries);
            unset($this->entries[$oldest]);
        }

        return true;
    }

    public function delete(string $key): bool
    {
        self::validateKey($key);
        unset($this->entries[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->entries = [];

        return true;
    }

    /**
     * @param iterable<string> $keys
     *
     * @return iterable<string, mixed>
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }

        return $values;
    }

    /**
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(iterable $values, int|\DateInterval|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    /**
     * @param iterable<string> $keys
     */
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    /**
     * Entries expire with time and reads refresh their recency: two reads of a key can differ.
     *
     * @phpstan-impure
     */
    public function has(string $key): bool
    {
        return $this->get($key, $this) !== $this;
    }

    /**
     * Returns the current number of entries, including possibly stale ones
     * that have not been read since expiring. It is primarily useful in tests.
     */
    public function size(): int
    {
        return \count($this->entries);
    }

    /**
     * Converts a PSR-16 TTL to seconds; null means "no explicit TTL" and
     * resolves to the cache's default.
     */
    private function ttlToSeconds(int|\DateInterval|null $ttl): int
    {
        if (null === $ttl) {
            return $this->defaultTtl;
        }
        if ($ttl instanceof \DateInterval) {
            $now = new \DateTimeImmutable();

            return $now->add($ttl)->getTimestamp() - $now->getTimestamp();
        }

        return $ttl;
    }

    private static function validateKey(string $key): void
    {
        if ('' === $key) {
            throw new InvalidCacheKeyException('cache key must not be empty');
        }
        if (1 === preg_match('#[{}()/\\\\@:]#', $key)) {
            throw new InvalidCacheKeyException(\sprintf('cache key "%s" contains reserved characters {}()/\\@:', $key));
        }
    }
}
