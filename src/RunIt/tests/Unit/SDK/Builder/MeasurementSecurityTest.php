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
use ItPack\RunIt\Contract\Domain\ArtifactType;
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
    public function testMeasurementsAreDroppedBeforeSamplingForAllRedactorModes(): void
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
                    $scope->measurements()->counter('secret-metric', 123);
                },
            );

            foreach ([$sampler->seen, $sink->published()] as $artifacts) {
                self::assertNotEmpty($artifacts, $mode);
                $encoded = json_encode(array_map(
                    static fn (ExecutionArtifactInterface $artifact): array => $artifact->normalize(),
                    $artifacts,
                ), JSON_THROW_ON_ERROR);
                self::assertStringNotContainsString('secret-metric', $encoded, $mode);
                foreach ($artifacts as $artifact) {
                    self::assertNotSame('measurement', ArtifactType::type($artifact), $mode);
                    self::assertArrayNotHasKey('value', $artifact->normalize(), $mode);
                }
            }

            self::assertSame([[
                'Default measurement classification dropped an unclassified measurement.',
                ['id' => 'redacted', 'type' => 'runit.artifact.redacted'],
            ]], $health, $mode);
        }
    }
}
