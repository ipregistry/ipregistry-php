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

use Ipregistry\Exception\ApiException;

/**
 * The ordered results of a batch IP lookup. Each entry may independently
 * succeed or fail; iterate the list to inspect each {@see IpInfoResult}, or
 * index into it with {@see at()}.
 *
 * @implements \IteratorAggregate<int, IpInfoResult>
 */
final readonly class IpInfoList implements \Countable, \IteratorAggregate
{
    /**
     * @param list<IpInfoResult> $results
     */
    public function __construct(
        public array $results = [],
    ) {
    }

    /**
     * Builds a list from the decoded {"results": [...]} envelope returned by
     * the API, mapping each element to either an IpInfo or an ApiException
     * depending on whether it carries an error code.
     *
     * @param array<array-key, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $results = [];
        foreach (Hydrator::arr($payload, 'results') as $entry) {
            if (!\is_array($entry)) {
                $results[] = IpInfoResult::failure(new ApiException('unexpected batch entry'));
                continue;
            }
            if (($entry['code'] ?? null) !== null) {
                $results[] = IpInfoResult::failure(ApiException::fromPayload($entry));
                continue;
            }
            $results[] = IpInfoResult::success(IpInfo::fromArray($entry));
        }

        return new self($results);
    }

    public function count(): int
    {
        return \count($this->results);
    }

    /**
     * Returns the IpInfo at index $index, or throws the error that entry
     * failed with.
     *
     * @throws ApiException         when the entry failed
     * @throws \OutOfRangeException when the index is out of range
     */
    public function at(int $index): IpInfo
    {
        if (!isset($this->results[$index])) {
            throw new \OutOfRangeException(\sprintf('index %d is out of range (list holds %d results)', $index, \count($this->results)));
        }

        return $this->results[$index]->get();
    }

    /**
     * @return \Iterator<int, IpInfoResult>
     */
    public function getIterator(): \Iterator
    {
        return new \ArrayIterator($this->results);
    }
}
