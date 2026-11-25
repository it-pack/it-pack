<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Exception;

use Codeception\Test\Unit;
use ItPack\RunIt\Domain\Exception\InvalidMeasurementValueException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InvalidMeasurementValueException::class)]
final class InvalidMeasurementValueExceptionTest extends Unit
{
    public function testForNonFiniteValue(): void
    {
        $exception = InvalidMeasurementValueException::forNonFiniteValue();

        self::assertSame('Measurement value must be finite.', $exception->getMessage());
    }
}
