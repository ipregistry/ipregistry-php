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
 * Representations of a country flag across several icon sets.
 */
final readonly class Flag
{
    public function __construct(
        public string $emoji = '',
        public string $emojiUnicode = '',
        public string $emojitwo = '',
        public string $noto = '',
        public string $twemoji = '',
        public string $wikimedia = '',
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            emoji: Hydrator::str($data, 'emoji'),
            emojiUnicode: Hydrator::str($data, 'emoji_unicode'),
            emojitwo: Hydrator::str($data, 'emojitwo'),
            noto: Hydrator::str($data, 'noto'),
            twemoji: Hydrator::str($data, 'twemoji'),
            wikimedia: Hydrator::str($data, 'wikimedia'),
        );
    }
}
