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
 * One entry of a batch IP lookup. Exactly one of $info and $error is
 * non-null: $info holds the data for a successfully resolved IP address, and
 * $error describes why that particular entry failed.
 */
final readonly class IpInfoResult
{
    private function __construct(
        public ?IpInfo $info,
        public ?ApiException $error,
    ) {
    }

    public static function success(IpInfo $info): self
    {
        return new self($info, null);
    }

    public static function failure(ApiException $error): self
    {
        return new self(null, $error);
    }

    /**
     * Returns the resolved data, or throws the error this entry failed with.
     *
     * @throws ApiException
     */
    public function get(): IpInfo
    {
        if (null !== $this->error) {
            throw $this->error;
        }

        \assert(null !== $this->info);

        return $this->info;
    }
}
