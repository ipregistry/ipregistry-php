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

namespace Ipregistry\Tests\Unit;

use Ipregistry\Enum\ErrorCode;
use Ipregistry\Exception\ApiException;
use Ipregistry\Exception\ClientException;
use Ipregistry\Exception\IpregistryException;
use PHPUnit\Framework\TestCase;

final class ErrorsTest extends TestCase
{
    public function testErrorCodeTryFromRaw(): void
    {
        self::assertSame(ErrorCode::InvalidIpAddress, ErrorCode::tryFromRaw('INVALID_IP_ADDRESS'));
        self::assertSame(ErrorCode::TooManyRequests, ErrorCode::tryFromRaw(' too_many_requests '));
        self::assertNull(ErrorCode::tryFromRaw('SOMETHING_ELSE'));
        self::assertNull(ErrorCode::tryFromRaw(''));
    }

    public function testApiExceptionFromPayload(): void
    {
        $exception = ApiException::fromPayload([
            'code' => 'INSUFFICIENT_CREDITS',
            'message' => 'You have exhausted your credits.',
            'resolution' => 'Purchase more credits.',
        ], 402);

        self::assertSame('INSUFFICIENT_CREDITS', $exception->rawCode);
        self::assertSame(ErrorCode::InsufficientCredits, $exception->errorCode);
        self::assertSame('Purchase more credits.', $exception->resolution);
        self::assertSame(402, $exception->status);
        self::assertSame(
            'ipregistry: You have exhausted your credits. (INSUFFICIENT_CREDITS): Purchase more credits.',
            $exception->getMessage(),
        );
    }

    public function testApiExceptionWithUnknownCodeKeepsRawCode(): void
    {
        $exception = ApiException::fromPayload(['code' => 'BRAND_NEW_CODE', 'message' => 'hi']);

        self::assertSame('BRAND_NEW_CODE', $exception->rawCode);
        self::assertNull($exception->errorCode);
        self::assertNull($exception->status);
    }

    public function testApiExceptionMessageFallsBackWhenEmpty(): void
    {
        $exception = new ApiException('');

        self::assertSame('ipregistry: API error', $exception->getMessage());
    }

    public function testExceptionsShareTheBaseType(): void
    {
        $cause = new \RuntimeException('boom');
        $clientException = new ClientException('request failed', $cause);

        self::assertInstanceOf(IpregistryException::class, $clientException);
        self::assertInstanceOf(IpregistryException::class, new ApiException('x'));
        self::assertSame('ipregistry: request failed', $clientException->getMessage());
        self::assertSame($cause, $clientException->getPrevious());
    }
}
