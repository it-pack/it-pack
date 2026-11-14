<?php

declare(strict_types=1);

namespace Tests\Unit\SDK\Builder;

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\DefaultArtifactRedactor;
use ItPack\RunIt\Application\Publication\DefaultMeasurementClassifier;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\Builder\RunItBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(RunItBuilder::class)]
final class MeasurementSecurityTest extends TestCase
{
    public function testValidMeasurementsAreDroppedForAllRedactorModes(): void
    {
        foreach (['default', 'null', 'custom'] as $mode) {
            $sink = new InMemoryArtifactPublisher();
            $health = [];
            $sampler = new class () implements SamplingPolicyInterface {
                /** @var list<ExecutionArtifactInterface> */
                public array $seen = [];

                public function sample(ExecutionArtifactInterface $artifact): bool
                {
                    $this->seen[] = $artifact;
                    return true;
                }
            };
            $builder = RunItBuilder::create()
                ->withPublisher($sink)
                ->withClock(new SystemMonotonicClock())
                ->withSequenceAllocator(new InMemorySequenceAllocator())
                ->withSnapshotSerializer(new JsonSnapshotSerializer())
                ->withIdentifierGenerator(new RandomIdentifierGenerator())
                ->withSamplingPolicy($sampler)
                ->withPublicationFailureHandler(
                    static function (Throwable $error, ?ExecutionArtifactInterface $artifact = null) use (
                        &$health,
                    ): void {
                        $health[] = [$error->getMessage(), $artifact?->normalize()];
                    }
                )
            ;
            if ($mode !== 'default') {
                $builder = $builder->withRedactor($mode === 'null' ? null : new DefaultArtifactRedactor());
            }

            $builder->build()->run(
                ExecutionDefinition::create(ExecutionName::fromString('test'), ExecutionKind::fromString('operation')),
                static function (ExecutionScopeInterface $scope): void {
                    $scope->measurements()->counter('dogfood-metric', 123);
                },
            );

            // Valid measurements are dropped before sampling in all redactor modes.
            // Non-measurement artifacts (executions, occurrences, snapshots) are
            // still delivered; only measurements are dropped.
            $samplerMeasurements = array_filter(
                $sampler->seen,
                static fn (ExecutionArtifactInterface $artifact): bool => DefaultMeasurementClassifier::isMeasurement($artifact)
            );
            $sinkMeasurements = array_filter(
                $sink->published(),
                static fn (ExecutionArtifactInterface $artifact): bool => DefaultMeasurementClassifier::isMeasurement($artifact)
            );
            self::assertEmpty($samplerMeasurements, $mode);
            self::assertEmpty($sinkMeasurements, $mode);

            // A drop notice is reported for each dropped measurement.
            self::assertNotEmpty($health, $mode);
            self::assertStringContainsString('classification dropped', $health[0][0]);
        }
    }
}
