<?php

declare(strict_types=1);

namespace Tests\Unit\Conformance\Result;

use Codeception\Test\Unit;
use ItPack\RunIt\Conformance\Result\ConformanceViolation;
use ItPack\RunIt\Conformance\Result\ViolationSeverity;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ConformanceViolation::class)]
final class ConformanceViolationTest extends Unit
{
    public function testCreateErrorViolation(): void
    {
        $violation = new ConformanceViolation('rule-1', 'something is wrong');

        self::assertSame('rule-1', $violation->id());
        self::assertTrue($violation->isError());
        self::assertSame('error', $violation->severity());
        self::assertSame('something is wrong', $violation->message());
        self::assertSame([], $violation->attributes());
        self::assertSame('[error] rule-1: something is wrong', $violation->__toString());
    }

    public function testCreateWarningViolation(): void
    {
        $violation = new ConformanceViolation('rule-2', 'be careful', ViolationSeverity::Warning);

        self::assertSame('rule-2', $violation->id());
        self::assertFalse($violation->isError());
        self::assertSame('warning', $violation->severity());
        self::assertSame('be careful', $violation->message());
        self::assertSame([], $violation->attributes());
        self::assertSame('[warning] rule-2: be careful', $violation->__toString());
    }

    public function testCreateWithAttributes(): void
    {
        $violation = new ConformanceViolation('rule-3', 'has attributes', ViolationSeverity::Warning);

        self::assertSame('rule-3', $violation->id());
        self::assertFalse($violation->isError());
        self::assertSame('warning', $violation->severity());
        self::assertSame([], $violation->attributes());
    }
}
