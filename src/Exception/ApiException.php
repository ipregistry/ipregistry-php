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

namespace Ipregistry\Exception;

use Ipregistry\Enum\ErrorCode;

/**
 * Thrown when the Ipregistry API reports a failure, such as an invalid IP
 * address, an exhausted credit balance, or throttling. It carries both the
 * raw code and, when recognized, the typed {@see ErrorCode}.
 *
 * In batch lookups, an ApiException may also describe the failure of a single
 * entry rather than the whole request (see IpInfoList and UserAgentList).
 */
final class ApiException extends IpregistryException
{
    public function __construct(
        string $message,
        /** The raw error code returned by the API, or an empty string. */
        public readonly string $rawCode = '',
        /** The typed form of the raw code, or null if it is not recognized. */
        public readonly ?ErrorCode $errorCode = null,
        /** A suggestion on how to resolve the error, when available. */
        public readonly string $resolution = '',
        /** The HTTP status of the response, or null for batch entry errors. */
        public readonly ?int $status = null,
    ) {
        parent::__construct(self::formatMessage($message, $this->rawCode, $this->resolution));
    }

    /**
     * Builds an ApiException from a decoded API error payload of the form
     * {"code": "...", "message": "...", "resolution": "..."}.
     *
     * @param array<array-key, mixed> $payload
     */
    public static function fromPayload(array $payload, ?int $status = null): self
    {
        $rawCode = \is_string($payload['code'] ?? null) ? $payload['code'] : '';
        $message = \is_string($payload['message'] ?? null) ? $payload['message'] : '';
        $resolution = \is_string($payload['resolution'] ?? null) ? $payload['resolution'] : '';

        return new self(
            message: $message,
            rawCode: $rawCode,
            errorCode: ErrorCode::tryFromRaw($rawCode),
            resolution: $resolution,
            status: $status,
        );
    }

    private static function formatMessage(string $message, string $rawCode, string $resolution): string
    {
        $formatted = 'ipregistry: '.('' !== $message ? $message : 'API error');
        if ('' !== $rawCode) {
            $formatted .= ' ('.$rawCode.')';
        }
        if ('' !== $resolution) {
            $formatted .= ': '.$resolution;
        }

        return $formatted;
    }
}
