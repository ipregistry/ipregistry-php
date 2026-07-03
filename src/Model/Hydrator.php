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
 * Tolerant extraction of typed values from decoded API payloads. Absent,
 * null, or mistyped fields yield safe defaults so that hydration never
 * throws on unexpected data.
 *
 * @internal
 */
final class Hydrator
{
    private function __construct()
    {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function str(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return \is_scalar($value) ? (string) $value : '';
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function bool(array $data, string $key): bool
    {
        return (bool) ($data[$key] ?? false);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function int(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function intOrNull(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function float(array $data, string $key): float
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (float) $value : 0.0;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function floatOrNull(array $data, string $key): ?float
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    public static function arr(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        return \is_array($value) ? $value : [];
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return list<string>
     */
    public static function strList(array $data, string $key): array
    {
        $values = [];
        foreach (self::arr($data, $key) as $value) {
            if (\is_scalar($value)) {
                $values[] = (string) $value;
            }
        }

        return $values;
    }
}
