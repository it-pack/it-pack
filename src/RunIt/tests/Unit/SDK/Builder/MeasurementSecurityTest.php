<?php

declare(strict_types=1);

namespace Tests\Unit\SDK\Builder;

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\DefaultArtifactRedactor;
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
    public function testValidMeasurementsAreClassifiedAndDeliveredForAllRedactorModes(): void
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

            // Valid measurements are classified and delivered to both sampler and sink.
            self::assertNotEmpty($sampler->seen, $mode);
            self::assertNotEmpty($sink->published(), $mode);

            $encoded = json_encode(array_map(
                static fn (ExecutionArtifactInterface $artifact): array => $artifact->normalize(),
                array_merge($sampler->seen, $sink->published()),
            ), JSON_THROW_ON_ERROR);
            self::assertStringContainsString('dogfood-metric', $encoded, $mode);

            // No drop notice for valid measurements.
            self::assertSame([], $health, $mode);
        }
    }
}
