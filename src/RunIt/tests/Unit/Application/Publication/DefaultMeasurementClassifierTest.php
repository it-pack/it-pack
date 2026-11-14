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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use RuntimeException;
use Throwable;

#[CoversClass(DefaultMeasurementClassifier::class)]
#[UsesClass(InMemoryArtifactPublisher::class)]
#[UsesClass(CallbackHealthReporter::class)]
#[UsesClass(VerifiedArtifactProjection::class)]
final class DefaultMeasurementClassifierTest extends Unit
{
    public function testMeasurementsAreDroppedAndReportedWithoutTheirFields(): void
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
            'timestamp' => 1, 'sequence' => 1, 'attributes' => [],
        ];
        $measurement = new VerifiedArtifactProjection($fields, 'measurement');
        $classifier->publish($measurement);

        self::assertCount(0, $sampler->published());
        self::assertCount(1, $reports);
        self::assertStringContainsString('classification dropped', $reports[0][0]);
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
