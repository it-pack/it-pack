<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\ClassifyingArtifactPublisher;
use ItPack\RunIt\Application\Publication\VerifiedArtifactProjection;
use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\RedactedArtifactInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Shared\DeterministicHashCalculator;
use ItPack\RunIt\Domain\Snapshot\Snapshot;
use ItPack\RunIt\Domain\Snapshot\SnapshotClassification;
use ItPack\RunIt\Domain\Snapshot\SnapshotId;
use ItPack\RunIt\Domain\Snapshot\SnapshotType;
use ItPack\RunIt\SDK\Builder\RunItBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClassifyingArtifactPublisher::class)]
final class SnapshotClassificationOptOutTest extends TestCase
{
    public function testStandaloneTerminalSnapshotRetainsPayloadHashAndTypedContract(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $snapshot = Snapshot::create(
            SnapshotId::fromString('snap'),
            ExecutionId::fromString('exec'),
            SnapshotType::fromString('execution.terminal'),
            1,
            ['business' => 'value'],
            new DeterministicHashCalculator(),
            classification: SnapshotClassification::Terminal,
        );
        new ClassifyingArtifactPublisher($sink)->publish($snapshot);

        self::assertCount(1, $sink->published());
        self::assertInstanceOf(SnapshotInterface::class, $sink->published()[0]);
        self::assertEqualsCanonicalizing($snapshot->normalize(), $sink->published()[0]->normalize());
    }

    public function testSdkDefaultAndExplicitRedactorChoices(): void
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
            if ($mode === 'null') {
                $builder = $builder->withRedactor(null);
            } elseif ($mode === 'custom') {
                $builder = $builder->withRedactor(new class () implements RedactorInterface {
                    public function redact(ExecutionArtifactInterface $artifact): ExecutionArtifactInterface
                    {
                        return $artifact;
                    }
                });
            }

            $builder->build()->run(
                ExecutionDefinition::create(ExecutionName::fromString('test'), ExecutionKind::fromString('operation')),
                static fn (): string => 'result',
            );

            foreach ([$sampler->seen, $sink->published()] as $artifacts) {
                $isTerminalSnapshot = static fn (ExecutionArtifactInterface $artifact): bool => (
                    $artifact instanceof SnapshotInterface
                    || (($artifact instanceof VerifiedArtifactProjection
                            || $artifact instanceof RedactedArtifactInterface)
                        && $artifact->artifactType() === 'snapshot')
                ) && ($artifact->normalize()['classification'] ?? null) === SnapshotClassification::Terminal->value;

                $snapshots = array_values(array_filter($artifacts, $isTerminalSnapshot));
                self::assertCount(1, $snapshots, $mode);
                $fields = $snapshots[0]->normalize();
                self::assertArrayNotHasKey('payload', $fields, $mode);
                self::assertArrayNotHasKey('content_hash', $fields, $mode);
                self::assertSame('terminal', $fields['classification'], $mode);
            }
        }
    }
}
