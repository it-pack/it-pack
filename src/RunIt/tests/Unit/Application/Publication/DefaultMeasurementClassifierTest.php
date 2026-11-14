<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\CallbackHealthReporter;
use ItPack\RunIt\Application\Publication\DefaultMeasurementClassifier;
use ItPack\RunIt\Application\Publication\VerifiedArtifactProjection;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Measurement\Measurement;
use ItPack\RunIt\Domain\Measurement\MeasurementName;
use ItPack\RunIt\Domain\Measurement\MeasurementUnit;
use ItPack\RunIt\Domain\Shared\ArtifactSequence;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use RuntimeException;
use Throwable;

#[CoversClass(DefaultMeasurementClassifier::class)]
#[UsesClass(InMemoryArtifactPublisher::class)]
#[UsesClass(CallbackHealthReporter::class)]
#[UsesClass(Measurement::class)]
final class DefaultMeasurementClassifierTest extends Unit
{
    public function testValidMeasurementIsClassifiedAndDelivered(): void
    {
        $reports = [];
        $health = new CallbackHealthReporter(static function (
            Throwable $error,
            ExecutionArtifactInterface $artifact,
        ) use (&$reports): void {
            $reports[] = [$error->getMessage(), $artifact->normalize()];
        });
        $sampler = new InMemoryArtifactPublisher();
        $classifier = new DefaultMeasurementClassifier($sampler, $health);

        $measurement = Measurement::counter(
            MeasurementName::fromString('runit.test.counter'),
            ExecutionId::fromString('export-execution'),
            42,
            null,
            Instant::of(1),
            ArtifactSequence::of(1),
            AttributeCollection::fromArray([]),
            MeasurementUnit::Count,
        );
        $classifier->publish($measurement);

        self::assertCount(1, $sampler->published());
        $delivered = $sampler->published()[0];
        self::assertInstanceOf(MeasurementInterface::class, $delivered);
        self::assertSame('runit.test.counter', $delivered->name()->toString());
        self::assertSame(42, $delivered->value());
        self::assertSame('count', $delivered->unit()->label());
        self::assertSame('export-execution', $delivered->executionId()->toString());
        self::assertNull($delivered->activityId());
        self::assertSame(1, $delivered->timestamp());
        self::assertSame(1, $delivered->sequence());
        self::assertSame([], $delivered->attributes());
        self::assertCount(0, $reports);
    }

    public function testDurationMeasurementIsClassifiedAndDelivered(): void
    {
        $reports = [];
        $health = new CallbackHealthReporter(static function (
            Throwable $error,
            ExecutionArtifactInterface $artifact,
        ) use (&$reports): void {
            $reports[] = [$error->getMessage(), $artifact->normalize()];
        });
        $sampler = new InMemoryArtifactPublisher();
        $classifier = new DefaultMeasurementClassifier($sampler, $health);

        $measurement = Measurement::duration(
            MeasurementName::fromString('runit.test.duration'),
            ExecutionId::fromString('export-execution'),
            1.5,
            null,
            Instant::of(1),
            ArtifactSequence::of(2),
            AttributeCollection::fromArray([]),
            MeasurementUnit::Milliseconds,
        );
        $classifier->publish($measurement);

        self::assertCount(1, $sampler->published());
        $delivered = $sampler->published()[0];
        self::assertInstanceOf(MeasurementInterface::class, $delivered);
        self::assertSame('runit.test.duration', $delivered->name()->toString());
        self::assertSame(1.5, $delivered->value());
        self::assertSame('milliseconds', $delivered->unit()->label());
        self::assertSame('export-execution', $delivered->executionId()->toString());
        self::assertNull($delivered->activityId());
        self::assertSame(1, $delivered->timestamp());
        self::assertSame(2, $delivered->sequence());
        self::assertSame([], $delivered->attributes());
        self::assertCount(0, $reports);
    }

    public function testMeasurementWithInvalidKindIsDropped(): void
    {
        $reports = [];
        $health = new CallbackHealthReporter(static function (
            Throwable $error,
            ExecutionArtifactInterface $artifact,
        ) use (&$reports): void {
            $reports[] = [$error->getMessage(), $artifact->normalize()];
        });
        $sampler = new InMemoryArtifactPublisher();
        $classifier = new DefaultMeasurementClassifier($sampler, $health);
        $fields = [
            'name' => 'test-metric', 'kind' => 'invalid-kind', 'unit' => 'count',
            'value' => 123, 'execution_id' => 'export-execution', 'activity_id' => null,
            'timestamp' => 1, 'sequence' => 1, 'attributes' => [],
        ];
        $measurement = new VerifiedArtifactProjection($fields, 'measurement');
        $classifier->publish($measurement);

        self::assertCount(0, $sampler->published());
        self::assertCount(1, $reports);
        self::assertStringContainsString('classification failed', $reports[0][0]);
        self::assertSame($fields, $measurement->normalize());
    }

    public function testMeasurementMissingRequiredFieldIsDropped(): void
    {
        $reports = [];
        $health = new CallbackHealthReporter(static function (
            Throwable $error,
            ExecutionArtifactInterface $artifact,
        ) use (&$reports): void {
            $reports[] = [$error->getMessage(), $artifact->normalize()];
        });
        $sampler = new InMemoryArtifactPublisher();
        $classifier = new DefaultMeasurementClassifier($sampler, $health);
        $fields = [
            'name' => 'test-metric', 'kind' => 'counter', 'unit' => 'count',
            'value' => 123, 'execution_id' => 'export-execution', 'activity_id' => null,
            'timestamp' => 1, 'sequence' => 1,
            // 'attributes' missing
        ];
        $measurement = new VerifiedArtifactProjection($fields, 'measurement');
        $classifier->publish($measurement);

        self::assertCount(0, $sampler->published());
        self::assertCount(1, $reports);
        self::assertStringContainsString('classification failed', $reports[0][0]);
        self::assertSame($fields, $measurement->normalize());
    }

    public function testOtherArtifactsPassThrough(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $classifier = new DefaultMeasurementClassifier($sink, null);
        $artifact = new VerifiedArtifactProjection(['execution_id' => 'export-execution'], 'observation');
        $classifier->publish($artifact);

        self::assertSame([$artifact], $sink->published());
    }

    public function testThrowingReporterFallbackDoesNotExposeItsMessage(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'runit-measurement-');
        $previous = (string) ini_get('error_log');
        try {
            ini_set('error_log', $path);
            $health = new class () implements InternalHealthReporterInterface {
                public function report(Throwable $error, ExecutionArtifactInterface $artifact): void
                {
                    throw new RuntimeException('secret-reporter-message');
                }
            };
            $sink = new InMemoryArtifactPublisher();
            new DefaultMeasurementClassifier($sink, $health)->publish(
                new VerifiedArtifactProjection([], 'measurement'),
            );
            $log = (string) file_get_contents($path);
            self::assertStringContainsString('health reporter failed', $log);
            self::assertStringNotContainsString('secret-reporter-message', $log);
            self::assertCount(0, $sink->published());
        } finally {
            ini_set('error_log', $previous);
            unlink($path);
        }
    }
}
