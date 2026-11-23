<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\OpenTelemetry\Telemetry;

use Codeception\Test\Unit;
use OpenTelemetry\SemConv\Attributes\ExceptionAttributes;
use PHPUnit\Framework\Attributes\CoversNothing;
use ReflectionClass;

/**
 * Wire-contract verification: every OpenTelemetry semantic-convention
 * exception attribute constant used by the RunIt bridge must match its
 * literal attribute key. Changing a constant here changes the exported span
 * event attributes.
 */
#[CoversNothing]
final class SemConvExceptionAttributesTest extends Unit
{
    public function testConstantsMatchTheirLiteralKeys(): void
    {
        $reflection = new ReflectionClass(ExceptionAttributes::class);
        $constants = $reflection->getConstants();

        self::assertSame('exception.type', $constants['EXCEPTION_TYPE']);
        self::assertSame('exception.message', $constants['EXCEPTION_MESSAGE']);
        self::assertSame('exception.stacktrace', $constants['EXCEPTION_STACKTRACE']);
    }
}
