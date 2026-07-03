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

namespace Ipregistry\Http;

use Ipregistry\Exception\ClientException;

/**
 * Default transport built on the cURL extension. It keeps a single cURL
 * handle for the lifetime of the transport so that connections to the API
 * are reused across requests.
 */
final class CurlTransport implements TransportInterface
{
    private ?\CurlHandle $handle = null;

    /**
     * @param float $connectTimeout the connection timeout in seconds
     */
    public function __construct(
        private readonly float $connectTimeout = 10.0,
    ) {
    }

    public function __destruct()
    {
        $this->close();
    }

    public function request(string $method, string $url, array $headers, ?string $body, float $timeout): TransportResponse
    {
        $handle = $this->handle();

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name.': '.$value;
        }

        /** @var array<string, string> $responseHeaders */
        $responseHeaders = [];

        $options = [
            \CURLOPT_URL => $url,
            \CURLOPT_CUSTOMREQUEST => $method,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_FOLLOWLOCATION => false,
            \CURLOPT_HTTPHEADER => $headerLines,
            \CURLOPT_CONNECTTIMEOUT_MS => (int) round($this->connectTimeout * 1000),
            \CURLOPT_TIMEOUT_MS => $timeout > 0 ? (int) round($timeout * 1000) : 0,
            // Advertise and transparently decode all encodings cURL supports.
            \CURLOPT_ENCODING => '',
            \CURLOPT_HEADERFUNCTION => static function (\CurlHandle $handle, string $line) use (&$responseHeaders): int {
                // A new status line starts a new header block (e.g. after a
                // "100 Continue" interim response); discard the previous block.
                if (str_starts_with($line, 'HTTP/')) {
                    $responseHeaders = [];

                    return \strlen($line);
                }
                $separator = strpos($line, ':');
                if (false !== $separator) {
                    $name = strtolower(trim(substr($line, 0, $separator)));
                    $responseHeaders[$name] = trim(substr($line, $separator + 1));
                }

                return \strlen($line);
            },
        ];
        if (null !== $body) {
            $options[\CURLOPT_POSTFIELDS] = $body;
        }

        curl_reset($handle);
        curl_setopt_array($handle, $options);

        $responseBody = curl_exec($handle);
        if (!\is_string($responseBody)) {
            $errno = curl_errno($handle);
            $error = curl_error($handle);

            throw new ClientException(\sprintf('request failed: %s (cURL error %d)', '' !== $error ? $error : 'unknown error', $errno));
        }

        $statusCode = (int) curl_getinfo($handle, \CURLINFO_RESPONSE_CODE);

        return new TransportResponse($statusCode, $responseHeaders, $responseBody);
    }

    /**
     * Releases the underlying cURL handle and its idle connections. The
     * transport transparently re-creates the handle if used again.
     */
    public function close(): void
    {
        // Dropping the last reference frees the handle and its connections
        // (curl_close() has no effect since PHP 8.0 and is deprecated in 8.5).
        $this->handle = null;
    }

    private function handle(): \CurlHandle
    {
        if (null === $this->handle) {
            $handle = curl_init();
            if (false === $handle) {
                throw new ClientException('failed to initialize cURL');
            }
            $this->handle = $handle;
        }

        return $this->handle;
    }
}
