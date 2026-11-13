<?php

declare(strict_types=1);

namespace Tests\Unit\SDK\Builder;

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
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
    public function testDefaultDropsMeasurementsBeforeSamplerAndExplicitNullOptOutDoesNot(): void
    {
        foreach ([false, true] as $optOut) {
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
            if ($optOut) {
                $builder = $builder->withRedactor(null);
            }

            $builder->build()->run(
                ExecutionDefinition::create(ExecutionName::fromString('test'), ExecutionKind::fromString('operation')),
                static function (ExecutionScopeInterface $scope): void {
                    $scope->measurements()->counter('secret-metric', 123);
                },
            );

            foreach ([$sampler->seen, $sink->published()] as $artifacts) {
                $encoded = json_encode(array_map(
                    static fn (ExecutionArtifactInterface $artifact): array => $artifact->normalize(),
                    $artifacts,
                ), JSON_THROW_ON_ERROR);
                if ($optOut) {
                    self::assertStringContainsString('secret-metric', $encoded);
                } else {
                    self::assertStringNotContainsString('secret-metric', $encoded);
                }
            }
        }
    }
}
