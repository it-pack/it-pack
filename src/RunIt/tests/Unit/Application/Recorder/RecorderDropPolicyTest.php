<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Recorder;

use Closure;
use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Application\Publication\ProvenanceAuthority;
use ItPack\RunIt\Application\Recorder\DefaultMeasurementRecorder;
use ItPack\RunIt\Application\Recorder\DefaultObservationRecorder;
use ItPack\RunIt\Application\Recorder\DefaultOccurrenceRecorder;
use ItPack\RunIt\Application\Recorder\DefaultSnapshotRecorder;
use ItPack\RunIt\Contract\Domain\ActivityIdInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\SnapshotStoreInterface;
use ItPack\RunIt\Domain\Activity\ActivityId;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Factory\StringIdentifierFactory;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\ArtifactField;
use ItPack\RunIt\Domain\Shared\DefaultThrowableMapper;
use ItPack\RunIt\Domain\Shared\DeterministicHashCalculator;
use ItPack\RunIt\Domain\Snapshot\SnapshotId;
use ItPack\RunIt\Domain\Snapshot\SnapshotType;
use RuntimeException;
use Tests\Support\Fixture\DeterministicMonotonicClock;
use Tests\Support\Fixture\SequenceIdentifierGenerator;

final class RecorderDropPolicyTest extends Unit
{
    public function testDropReturnsUnattestedArtifactsWithoutPublicationOrPersistence(): void
    {
        $publisher = $this->createMock(ArtifactPublisherInterface::class);
        $publisher->expects($this->never())->method('publish');
        $store = $this->createMock(SnapshotStoreInterface::class);
        $store->expects($this->never())->method('store');
        $authority = new ProvenanceAuthority();
        $calls = 0;
        $record = $this->recordings($publisher, $store, $authority, static function () use (&$calls): bool {
            ++$calls;
            return true;
        });

        foreach ($record as $operation) {
            $artifact = $operation();
            self::assertNull($authority->exportIdForArtifact($artifact));
        }

        self::assertSame(4, $calls);
    }

    public function testFalsePublishesAndPersists(): void
    {
        $publisher = $this->createMock(ArtifactPublisherInterface::class);
        $publisher->expects($this->exactly(4))->method('publish');
        $store = $this->createMock(SnapshotStoreInterface::class);
        $store->expects($this->once())->method('store');
        $operations = $this->recordings($publisher, $store, new ProvenanceAuthority(), static fn (): bool => false);
        foreach ($operations as $operation) {
            $operation();
        }
    }

    public function testPolicyExceptionsPropagateBeforePublicationOrPersistence(): void
    {
        $publisher = $this->createMock(ArtifactPublisherInterface::class);
        $publisher->expects($this->never())->method('publish');
        $store = $this->createMock(SnapshotStoreInterface::class);
        $store->expects($this->never())->method('store');
        $error = new RuntimeException('policy');
        $operations = $this->recordings(
            $publisher,
            $store,
            new ProvenanceAuthority(),
            static function () use ($error): bool {
                throw $error;
            },
        );
        foreach ($operations as $operation) {
            try {
                $operation();
                self::fail('Expected policy exception');
            } catch (RuntimeException $caught) {
                self::assertSame($error, $caught);
            }
        }
    }

    public function testCapturedActivityRemainsPairedAcrossReentrantPolicyWithDuplicateBusinessIds(): void
    {
        $authority = new ProvenanceAuthority();
        $authority->issueExportId('execution', 'execution', 'execution', 'execution');

        $first = $authority->issueExportId('activity', 'activity', 'execution', 'same');
        $second = $authority->issueExportId('activity', 'activity', 'execution', 'same');
        $activity = ActivityId::fromString('same');
        $current = $first;
        $calls = 0;
        $resolver = static function () use (&$calls, &$current, $activity): array {
            ++$calls;
            return [true, $activity, $current];
        };
        $policy = static function (?string $id, ?bool $valid) use (&$current, $second): bool {
            self::assertSame('same', $id);
            self::assertTrue($valid);
            $current = $second;
            return false;
        };
        $operations = $this->recordings(
            self::createStub(ArtifactPublisherInterface::class),
            self::createStub(SnapshotStoreInterface::class),
            $authority,
            $policy,
            $resolver,
            allMeasurements: true,
        );
        foreach ($operations as $operation) {
            $current = $first;
            $artifact = $operation();
            self::assertSame('same', $artifact->normalize()[ArtifactField::ACTIVITY_ID] ?? null);
            self::assertSame($first, $authority->exportIdentityFields($artifact)[ArtifactField::ACTIVITY_ID] ?? null);
        }

        self::assertSame(8, $calls);
    }

    public function testInvalidCaptureDoesNotFallBackToLegacyActivity(): void
    {
        $legacyCalls = 0;
        $legacy = static function () use (&$legacyCalls): ActivityIdInterface {
            ++$legacyCalls;
            return ActivityId::fromString('legacy');
        };
        $policyCalls = 0;
        $policy = static function (?string $id, ?bool $valid) use (&$policyCalls): bool {
            ++$policyCalls;
            self::assertNull($id);
            self::assertFalse($valid);
            return true;
        };
        $operations = $this->recordings(
            self::createStub(ArtifactPublisherInterface::class),
            self::createStub(SnapshotStoreInterface::class),
            new ProvenanceAuthority(),
            $policy,
            static fn (): array => [false, ActivityId::fromString('wrong'), 'wrong'],
            $legacy,
        );
        foreach ($operations as $operation) {
            self::assertNull($operation()->normalize()[ArtifactField::ACTIVITY_ID] ?? null);
        }

        self::assertSame(0, $legacyCalls);
        self::assertSame(4, $policyCalls);
    }

    public function testLegacyActivityHasNoVerifiedEdge(): void
    {
        $authority = new ProvenanceAuthority();
        $authority->issueExportId('execution', 'execution', 'execution', 'execution');
        $authority->issueExportId('activity', 'activity', 'execution', 'legacy');

        $operations = $this->recordings(
            self::createStub(ArtifactPublisherInterface::class),
            self::createStub(SnapshotStoreInterface::class),
            $authority,
            static function (?string $id, ?bool $valid): bool {
                self::assertSame('legacy', $id);
                self::assertNull($valid);
                return false;
            },
            legacy: static fn (): ActivityIdInterface => ActivityId::fromString('legacy'),
        );
        foreach ($operations as $operation) {
            $artifact = $operation();
            self::assertSame('legacy', $artifact->normalize()[ArtifactField::ACTIVITY_ID] ?? null);
            self::assertArrayNotHasKey(ArtifactField::ACTIVITY_ID, $authority->exportIdentityFields($artifact) ?? []);
        }
    }

    /**
     * @param Closure(?string, ?bool): bool $shouldDrop
     * @param null|Closure(): array{bool, ?ActivityIdInterface, ?string} $resolver
     * @param null|Closure(): ?ActivityIdInterface $legacy
     * @return list<Closure(): ExecutionArtifactInterface>
     */
    private function recordings(
        ArtifactPublisherInterface $publisher,
        SnapshotStoreInterface $store,
        ProvenanceAuthority $authority,
        Closure $shouldDrop,
        ?Closure $resolver = null,
        ?Closure $legacy = null,
        bool $allMeasurements = false,
    ): array {
        $id = ExecutionId::fromString('execution');
        $activity = $legacy ?? static fn (): null => null;
        $sequence = new InMemorySequenceAllocator();
        $clock = new DeterministicMonotonicClock(1_000, 0);
        $generator = new SequenceIdentifierGenerator('artifact');
        $measurement = new DefaultMeasurementRecorder(
            $id,
            $activity,
            $sequence,
            $clock,
            $publisher,
            $authority,
            $shouldDrop,
            $resolver,
        );
        $observation = new DefaultObservationRecorder(
            $id,
            $activity,
            $sequence,
            $clock,
            $publisher,
            new DefaultThrowableMapper(),
            new StringIdentifierFactory(ObservationId::fromString(...), $generator),
            $authority,
            $shouldDrop,
            $resolver,
        );
        $occurrence = new DefaultOccurrenceRecorder(
            $id,
            $activity,
            $sequence,
            $clock,
            $publisher,
            new StringIdentifierFactory(OccurrenceId::fromString(...), $generator),
            $authority,
            $shouldDrop,
            $resolver,
        );
        $snapshot = new DefaultSnapshotRecorder(
            $id,
            $activity,
            $sequence,
            $clock,
            $publisher,
            new JsonSnapshotSerializer(),
            new StringIdentifierFactory(SnapshotId::fromString(...), $generator),
            new DeterministicHashCalculator(),
            $store,
            null,
            $authority,
            $shouldDrop,
            $resolver,
        );

        $operations = [
            static fn (): MeasurementInterface => $measurement->counter('hits', 1),
            static fn (): ObservationInterface => $observation->record('message'),
            static fn (): OccurrenceInterface => $occurrence->record(OccurrenceType::fromString('app.event.v1'), 'app'),
            static fn (): SnapshotInterface => $snapshot->record(SnapshotType::fromString('app.state.v1'), []),
        ];
        if ($allMeasurements) {
            $operations[] = static fn (): MeasurementInterface => $measurement->gauge('gauge', 1.0);
            $operations[] = static fn (): MeasurementInterface => $measurement->duration('duration', 1.0);
            $operations[] = static fn (): MeasurementInterface => $measurement->size('size', 1);
            $operations[] = static fn (): MeasurementInterface => $measurement->ratio('ratio', 1.0);
        }

        return $operations;
    }
}
