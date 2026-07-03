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

/**
 * Runs PHP's built-in development server around a router script, playing
 * the role Go's net/http/httptest plays in the reference implementation:
 * an in-process fake of the Ipregistry API for offline behavior tests.
 */
final class BuiltInServer
{
    /** @var resource */
    private $process;

    /**
     * @param resource $process
     */
    private function __construct(
        $process,
        public readonly string $baseUrl,
    ) {
        $this->process = $process;
    }

    public static function start(string $routerScript): self
    {
        $lastError = 'unknown';
        for ($attempt = 0; $attempt < 10; ++$attempt) {
            $port = random_int(49152, 65535);
            $address = '127.0.0.1:'.$port;

            $process = proc_open(
                [\PHP_BINARY, '-S', $address, $routerScript],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
            );
            if (!\is_resource($process)) {
                $lastError = 'proc_open failed';
                continue;
            }

            // Wait until the server accepts connections.
            $deadline = microtime(true) + 5.0;
            while (microtime(true) < $deadline) {
                $status = proc_get_status($process);
                if (!$status['running']) {
                    break; // most likely the port is taken; retry on another one
                }
                $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
                if (\is_resource($socket)) {
                    fclose($socket);

                    return new self($process, 'http://'.$address);
                }
                usleep(50_000);
            }

            $lastError = 'server did not accept connections on '.$address;
            proc_terminate($process);
            proc_close($process);
        }

        throw new \RuntimeException('failed to start the built-in PHP server: '.$lastError);
    }

    public function stop(): void
    {
        proc_terminate($this->process);
        proc_close($this->process);
    }
}
