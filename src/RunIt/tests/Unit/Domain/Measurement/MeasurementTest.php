<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Measurement;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Contract\Exception\InvalidValueExceptionInterface;
use ItPack\RunIt\Contract\Exception\RunItExceptionInterface;
use ItPack\RunIt\Domain\Activity\ActivityId;
use ItPack\RunIt\Domain\Exception\InvalidMeasurementValueException;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Measurement\Measurement;
use ItPack\RunIt\Domain\Measurement\MeasurementKind;
use ItPack\RunIt\Domain\Measurement\MeasurementName;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;
use Tests\Support\Fixture\RunItTestKit;

final class MeasurementTest extends Unit
{
    public function testCounterRequiresIntegerValue(): void
    {
        $measurement = Measurement::counter(
            MeasurementName::fromString('files.loaded'),
            ExecutionId::fromString('exec-1'),
            42,
            instant: Instant::of(1000),
        );

        self::assertSame(MeasurementKind::Counter, $measurement->kind());
        self::assertSame(42, $measurement->value());
        self::assertSame(1000, $measurement->timestamp());
    }

    public function testGaugeRequiresFiniteFloatValue(): void
    {
        $measurement = Measurement::gauge(
            MeasurementName::fromString('cpu.usage'),
            ExecutionId::fromString('exec-1'),
            12.5,
            instant: Instant::of(1000),
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
        } catch (InvalidMeasurementValueException $invalidMeasurementValueException) {
            self::assertInstanceOf(RunItExceptionInterface::class, $invalidMeasurementValueException);
            self::assertInstanceOf(InvalidValueExceptionInterface::class, $invalidMeasurementValueException);
        }
    }

    public function testSizeAndRatioRecordersProduceMeasurementsWithTheirUnits(): void
    {
        $kit = new RunItTestKit();
        $sizes = [];
        $ratios = [];

        $kit->runner->run(
            ExecutionDefinition::create(
                ExecutionName::fromString('units'),
                ExecutionKind::fromString('task'),
            ),
            static function (ExecutionScopeInterface $scope) use (
                $kit,
                &$sizes,
                &$ratios
            ): void {
                $scope->measurements()->size('payload', 2048);
                $scope->measurements()->ratio('hit_rate', 0.75);

                foreach ($kit->artifacts() as $artifact) {
                    if ($artifact instanceof MeasurementInterface) {
                        $unit = $artifact->unit()->label();

                        if ($unit === 'bytes') {
                            $sizes[] = $artifact;
                        }

                        if ($unit === 'ratio') {
                            $ratios[] = $artifact;
                        }
                    }
                }
            },
        );

        self::assertCount(1, $sizes);
        self::assertCount(1, $ratios);
    }

    public function testActivityBoundMeasurementReportsActivityId(): void
    {
        $measurement = Measurement::duration(
            MeasurementName::fromString('latency'),
            ExecutionId::fromString('exec-1'),
            3.5,
            ActivityId::fromString('act-1'),
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
            instant: Instant::of(1000),
            attributes: AttributeCollection::empty()->with('region', 'eu'),
        );

        self::assertSame(['region' => 'eu'], $measurement->attributes());
    }

    public function testNormalizationIsDeterministic(): void
    {
        $measurement = Measurement::ratio(
            MeasurementName::fromString('success.rate'),
            ExecutionId::fromString('exec-1'),
            0.99,
            instant: Instant::of(1000),
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
