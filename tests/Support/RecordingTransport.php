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

namespace Ipregistry\Tests\Support;

use Ipregistry\Exception\ClientException;
use Ipregistry\Http\TransportInterface;
use Ipregistry\Http\TransportResponse;

/**
 * Scripted transport for behavior tests: records every request the client
 * makes and replays a queue of canned responses or exceptions.
 */
final class RecordingTransport implements TransportInterface
{
    /** @var list<array{method: string, url: string, headers: array<string, string>, body: ?string, timeout: float}> */
    public array $requests = [];

    /** @var list<TransportResponse|ClientException> */
    private array $queue = [];

    /**
     * @param array<string, string> $headers
     */
    public function queueResponse(int $status, string $body, array $headers = []): void
    {
        $lowercased = [];
        foreach ($headers as $name => $value) {
            $lowercased[strtolower($name)] = $value;
        }
        $this->queue[] = new TransportResponse($status, $lowercased, $body);
    }

    /**
     * @param array<array-key, mixed> $payload
     * @param array<string, string>   $headers
     */
    public function queueJson(int $status, array $payload, array $headers = []): void
    {
        $this->queueResponse($status, json_encode($payload, \JSON_THROW_ON_ERROR), $headers);
    }

    public function queueException(?ClientException $exception = null): void
    {
        $this->queue[] = $exception ?? new ClientException('simulated network error');
    }

    public function request(string $method, string $url, array $headers, ?string $body, float $timeout): TransportResponse
    {
        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
            'timeout' => $timeout,
        ];

        if ([] === $this->queue) {
            throw new \LogicException('RecordingTransport: no queued response for '.$method.' '.$url);
        }

        $next = array_shift($this->queue);
        if ($next instanceof ClientException) {
            throw $next;
        }

        return $next;
    }
}
