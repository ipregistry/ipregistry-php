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
 * Describes how monetary values are formatted for a currency.
 */
final readonly class CurrencyFormat
{
    public function __construct(
        public string $decimalSeparator = '',
        public string $groupSeparator = '',
        public CurrencyFormatAffix $negative = new CurrencyFormatAffix(),
        public CurrencyFormatAffix $positive = new CurrencyFormatAffix(),
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            decimalSeparator: Hydrator::str($data, 'decimal_separator'),
            groupSeparator: Hydrator::str($data, 'group_separator'),
            negative: CurrencyFormatAffix::fromArray(Hydrator::arr($data, 'negative')),
            positive: CurrencyFormatAffix::fromArray(Hydrator::arr($data, 'positive')),
        );
    }
}
