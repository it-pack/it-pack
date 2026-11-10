<?php

declare(strict_types=1);

namespace Tests\Unit\Contract\Exception;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Exception\InvalidCloudEventException;
use ItPack\RunIt\Contract\Exception\InvalidValueExceptionInterface;

/**
 * @group contract
 */
final class InvalidCloudEventExceptionTest extends Unit
{
    public function testImplementsMarkerInterface(): void
    {
        self::assertInstanceOf(InvalidValueExceptionInterface::class, new InvalidCloudEventException('x'));
    }

    public function testMissingField(): void
    {
        $expected = 'A CloudEvent must contain the required field "id".';

        self::assertSame($expected, InvalidCloudEventException::missingField('id')->getMessage());
    }

    public function testEmptyField(): void
    {
        $expected = 'CloudEvent field "specversion" must not be empty.';

        self::assertSame($expected, InvalidCloudEventException::emptyField('specversion')->getMessage());
    }

    public function testUnsupportedExtensionKey(): void
    {
        $expected = 'lower-case letters, digits, and optional hyphens';

        self::assertStringContainsString(
            $expected,
            InvalidCloudEventException::unsupportedExtensionKey('Bad Key')->getMessage(),
        );
    }

    public function testMalformedTime(): void
    {
        $expected = 'RFC 3339 timestamp';

        self::assertStringContainsString(
            $expected,
            InvalidCloudEventException::malformedTime('not-a-time')->getMessage(),
        );
    }

    public function testInvalidSpecVersion(): void
    {
        $expected = 'Unsupported CloudEvents specversion "0.9".';

        self::assertSame($expected, InvalidCloudEventException::invalidSpecVersion('0.9')->getMessage());
    }

    public function testEventTooLarge(): void
    {
        $expected = 'Serialized CloudEvent is 5000 bytes, exceeding the 1024 byte limit.';

        self::assertSame($expected, InvalidCloudEventException::eventTooLarge(5000, 1024)->getMessage());
    }

    public function testUnserializableData(): void
    {
        $expected = 'CloudEvent data could not be serialized to JSON.';

        self::assertSame($expected, InvalidCloudEventException::unserializableData()->getMessage());
    }

    public function testMalformedJson(): void
    {
        $expected = 'CloudEvent payload is not valid JSON.';

        self::assertSame($expected, InvalidCloudEventException::malformedJson()->getMessage());
    }
}
