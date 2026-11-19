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

#[CoversClass(RunItBuilder::class)]
final class MeasurementSecurityTest extends TestCase
{
    public function testValidMeasurementsAreClassifiedAndDeliveredForAllRedactorModes(): void
    {
        foreach (['default', 'null', 'custom'] as $mode) {
            $sink = new InMemoryArtifactPublisher();
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

            // Valid measurements pass classification and reach the sink.
            $measurements = array_values(array_filter(
                $sink->published(),
                static fn (ExecutionArtifactInterface $a): bool =>
                    ($a->normalize()['name'] ?? null) === 'dogfood-metric',
            ));
            self::assertNotEmpty($measurements, $mode);
            self::assertSame(123, $measurements[0]->normalize()['value'] ?? null, $mode);

            // Sampler sees the classified measurement too.
            $samplerMeasurements = array_values(array_filter(
                $sampler->seen,
                static fn (ExecutionArtifactInterface $a): bool =>
                    ($a->normalize()['name'] ?? null) === 'dogfood-metric',
            ));
            self::assertNotEmpty($samplerMeasurements, $mode);
        }
    }
}
