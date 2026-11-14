<?php

declare(strict_types=1);

namespace Tests\Unit\Conformance\Result;

use ItPack\AsyncIt\Conformance\Result\ConformanceResult;
use ItPack\AsyncIt\Conformance\Result\ConformanceViolation;
use ItPack\AsyncIt\Conformance\Result\ViolationSeverity;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Conformance\Result\ConformanceResult
 * @covers \ItPack\AsyncIt\Conformance\Result\ConformanceViolation
 * @covers \ItPack\AsyncIt\Conformance\Result\ViolationSeverity
 */
final class ConformanceResultTest extends TestCase
{
    public function testEmptyResultIsConformant(): void
    {
        $result = new ConformanceResult('Scheduler');

        self::assertSame('Scheduler', $result->port);
        self::assertTrue($result->isConformant());
        self::assertCount(0, $result);
        self::assertSame([], $result->errors());
        self::assertSame([], $result->warnings());
        self::assertStringContainsString('PASS', $result->summary());
    }

    public function testErrorViolationFailsConformance(): void
    {
        $result = new ConformanceResult('Scheduler', [
            new ConformanceViolation('ctx-bleed', 'context leaked across suspend/resume'),
        ]);

        self::assertFalse($result->isConformant());
        self::assertCount(1, $result->errors());
        self::assertCount(0, $result->warnings());
        self::assertStringContainsString('FAIL', $result->summary());
    }

    public function testWarningDoesNotFailConformance(): void
    {
        $warning = new ConformanceViolation(
            'slow-publish',
            'publisher buffered within probe window',
            ViolationSeverity::Warning,
        );

        $result = new ConformanceResult('Scheduler')->add($warning);

        self::assertTrue($result->isConformant());
        self::assertCount(1, $result->warnings());
        self::assertCount(0, $result->errors());
    }

    public function testIterationYieldsEveryViolation(): void
    {
        $result = new ConformanceResult('Scheduler', [
            new ConformanceViolation('a', 'first'),
            new ConformanceViolation('b', 'second', ViolationSeverity::Warning),
        ]);

        $collected = [];
        foreach ($result as $violation) {
            self::assertInstanceOf(ConformanceViolation::class, $violation);
            $collected[] = $violation->id;
        }

        self::assertSame(['a', 'b'], $collected);
    }

    public function testViolationStringFormatsSeverity(): void
    {
        $violation = new ConformanceViolation('x', 'boom', ViolationSeverity::Error);

        self::assertSame('[error] x: boom', (string) $violation);
        self::assertTrue($violation->isError());
    }
}
