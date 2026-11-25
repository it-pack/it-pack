<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Measurement;

use Codeception\Test\Unit;
use ItPack\RunIt\Domain\Measurement\MeasurementKind;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(MeasurementKind::class)]
final class MeasurementKindTest extends Unit
{
    public function testCasesHaveValues(): void
    {
        self::assertSame('counter', MeasurementKind::Counter->value);
        self::assertSame('gauge', MeasurementKind::Gauge->value);
        self::assertSame('duration', MeasurementKind::Duration->value);
        self::assertSame('size', MeasurementKind::Size->value);
        self::assertSame('ratio', MeasurementKind::Ratio->value);
    }

    public function testLabelReturnsValue(): void
    {
        self::assertSame('gauge', MeasurementKind::Gauge->label());
    }
}
