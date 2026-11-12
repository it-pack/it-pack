<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\OpenTelemetry\Telemetry;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\AttributeValues;
use ReflectionClass;

/**
 * Wire-contract verification: every AttributeValues constant must match its
 * literal OpenTelemetry attribute spelling and type. Changing a value here
 * changes the exported wire format.
 */
final class AttributeValuesTest extends Unit
{
    public function testConstantsMatchTheirLiteralValuesAndTypes(): void
    {
        $reflection = new ReflectionClass(AttributeValues::class);
        $constants = $reflection->getConstants();

        self::assertSame('redacted', $constants['REDACTED_ID']);
        self::assertIsString($constants['REDACTED_ID']);

        self::assertSame('runit.artifact.redacted', $constants['REDACTED_TYPE']);
        self::assertIsString($constants['REDACTED_TYPE']);

        self::assertSame('runit://redacted', $constants['REDACTED_SOURCE']);
        self::assertIsString($constants['REDACTED_SOURCE']);

        self::assertSame('unknown', $constants['UNKNOWN_CLASSIFICATION']);
        self::assertIsString($constants['UNKNOWN_CLASSIFICATION']);

        self::assertSame(true, $constants['REDACTED_MARKER']);
        self::assertIsBool($constants['REDACTED_MARKER']);

        self::assertSame('', $constants['EMPTY_IDENTITY']);
        self::assertIsString($constants['EMPTY_IDENTITY']);

        self::assertSame('', $constants['EMPTY_KIND_STATUS']);
        self::assertIsString($constants['EMPTY_KIND_STATUS']);

        self::assertSame('', $constants['EMPTY_EXCEPTION_DETAIL']);
        self::assertIsString($constants['EMPTY_EXCEPTION_DETAIL']);

        self::assertSame('execution', $constants['DEFAULT_EXECUTION_NAME']);
        self::assertIsString($constants['DEFAULT_EXECUTION_NAME']);

        self::assertSame('activity', $constants['DEFAULT_ACTIVITY_NAME']);
        self::assertIsString($constants['DEFAULT_ACTIVITY_NAME']);
    }
}
