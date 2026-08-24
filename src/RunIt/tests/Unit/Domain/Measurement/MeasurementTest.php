<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Measurement;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Exception\InvalidValueExceptionInterface;
use ItPack\RunIt\Contract\Exception\RunItExceptionInterface;
use ItPack\RunIt\Domain\Exception\InvalidMeasurementValueException;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Measurement\Measurement;
use ItPack\RunIt\Domain\Measurement\MeasurementName;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;

final class MeasurementTest extends Unit
{
    public function testCounterRequiresIntegerValue(): void
    {
        $measurement = Measurement::counter(
            MeasurementName::fromString('files.loaded'),
            ExecutionId::fromString('exec-1'),
            42,
            null,
            Instant::of(1000),
        );

        self::assertSame(\ItPack\RunIt\Domain\Measurement\MeasurementKind::Counter, $measurement->kind());
        self::assertSame(42, $measurement->value());
        self::assertSame(1000, $measurement->timestamp());
    }

    public function testGaugeRequiresFiniteFloatValue(): void
    {
        $measurement = Measurement::gauge(
            MeasurementName::fromString('cpu.usage'),
            ExecutionId::fromString('exec-1'),
            12.5,
            null,
            Instant::of(1000),
        );

        self::assertSame(12.5, $measurement->value());
    }

    public function testNonFiniteValueIsRejected(): void
    {
        $this->expectException(InvalidMeasurementValueException::class);
        $this->expectExceptionMessage('Measurement value must be finite.');

        Measurement::gauge(
            MeasurementName::fromString('cpu.usage'),
            ExecutionId::fromString('exec-1'),
            INF,
        );
    }

    public function testNonFiniteValueRejectionCarriesTheInvalidValueMarker(): void
    {
        try {
            Measurement::gauge(
                MeasurementName::fromString('cpu.usage'),
                ExecutionId::fromString('exec-1'),
                NAN,
            );
            self::fail('non-finite measurement values must be rejected');
        } catch (InvalidMeasurementValueException $exception) {
            self::assertInstanceOf(RunItExceptionInterface::class, $exception);
            self::assertInstanceOf(InvalidValueExceptionInterface::class, $exception);
        }
    }

    public function testActivityBoundMeasurementReportsActivityId(): void
    {
        $measurement = Measurement::duration(
            MeasurementName::fromString('latency'),
            ExecutionId::fromString('exec-1'),
            3.5,
            \ItPack\RunIt\Domain\Activity\ActivityId::fromString('act-1'),
            Instant::of(1000),
        );

        self::assertSame('act-1', $measurement->activityId()?->toString());
    }

    public function testAttributesAreCaptured(): void
    {
        $measurement = Measurement::size(
            MeasurementName::fromString('memory.peak'),
            ExecutionId::fromString('exec-1'),
            1024,
            null,
            Instant::of(1000),
            null,
            AttributeCollection::empty()->with('region', 'eu'),
        );

        self::assertSame(['region' => 'eu'], $measurement->attributes());
    }

    public function testNormalizationIsDeterministic(): void
    {
        $measurement = Measurement::ratio(
            MeasurementName::fromString('success.rate'),
            ExecutionId::fromString('exec-1'),
            0.99,
            null,
            Instant::of(1000),
        );

        $expected = [
            'name' => 'success.rate',
            'kind' => 'ratio',
            'unit' => 'ratio',
            'value' => 0.99,
            'execution_id' => 'exec-1',
            'activity_id' => null,
            'timestamp' => 1000,
            'sequence' => 0,
            'attributes' => [],
        ];

        self::assertSame($expected, $measurement->normalize());
    }
}
