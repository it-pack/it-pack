<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Recorder;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Memory\InMemorySnapshotStore;
use ItPack\RunIt\Application\Publication\ProvenanceAuthority;
use ItPack\RunIt\Application\Recorder\DefaultSnapshotRecorder;
use ItPack\RunIt\Contract\Domain\Factory\StringIdentifierFactoryInterface;
use ItPack\RunIt\Contract\Domain\HashCalculatorInterface;
use ItPack\RunIt\Contract\Domain\SnapshotIdInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Port\SerializerInterface;
use ItPack\RunIt\Contract\Port\SnapshotStoreInterface;
use ItPack\RunIt\Domain\Activity\ActivityId;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Snapshot\SnapshotId;
use ItPack\RunIt\Domain\Snapshot\SnapshotType;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Tests\Support\Fixture\DeterministicMonotonicClock;
use Tests\Support\Fixture\RecordingArtifactPublisher;
use Tests\Support\Fixture\RunItTestKit;
use Throwable;

/**
 * @template-implements StringIdentifierFactoryInterface<SnapshotIdInterface>
 */
final class TestSnapshotIdFactory implements StringIdentifierFactoryInterface
{
    public function build(): SnapshotIdInterface
    {
        return SnapshotId::fromString('snap-1');
    }
}

final class TestHashCalculator implements HashCalculatorInterface
{
    public function calculate(string $content): string
    {
        return 'hash';
    }
}

/**
 * @group application
 * @group recorder
 */
#[CoversClass(DefaultSnapshotRecorder::class)]
final class DefaultSnapshotRecorderTest extends Unit
{
    private RunItTestKit $kit;

    protected function setUp(): void
    {
        $this->kit = new RunItTestKit();
    }

    /**
     * @return list<SnapshotInterface>
     */
    private function snapshots(): array
    {
        return $this->kit->artifactsOf(SnapshotInterface::class);
    }

    public function testCheckpointSnapshotIsPublishedAndBoundToExecution(): void
    {
        $this->kit->runner->run(
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            function ($scope): null {
                $scope->snapshots()->record(SnapshotType::fromString('itpack.state.v1'), ['ok' => true]);

                return null;
            },
        );

        $userSnapshots = [];
        foreach ($this->snapshots() as $snapshot) {
            if ($snapshot->type()->toString() !== 'execution.terminal') {
                $userSnapshots[] = $snapshot;
            }
        }

        self::assertCount(1, $userSnapshots);
        self::assertSame('itpack.state.v1', $userSnapshots[0]->type()->toString());
        self::assertNull($userSnapshots[0]->activityId());
    }

    public function testSnapshotIsPersistedWhenStoreConfigured(): void
    {
        $store = new InMemorySnapshotStore();
        $kit = new RunItTestKit(snapshotStore: $store);

        $kit->runner->run(
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            function ($scope): null {
                $scope->snapshots()->record(SnapshotType::fromString('itpack.state.v1'), ['ok' => true]);

                return null;
            },
        );

        $stored = $kit->storedSnapshots();
        self::assertCount(1, $stored);
        self::assertSame('itpack.state.v1', $stored[0]->type()->toString());
    }

    public function testStoreFailureIsForwardedAndDoesNotBreakRecording(): void
    {
        $captured = null;
        $throwingStore = new class () implements SnapshotStoreInterface {
            public function store(SnapshotInterface $snapshot): void
            {
                throw new RuntimeException('store down');
            }
        };

        $kit = new RunItTestKit(
            snapshotStore: $throwingStore,
            onSnapshotStoreFailure: static function (Throwable $error) use (&$captured): void {
                $captured = $error;
            },
        );

        $recorded = $kit->runner->run(
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            fn ($scope) => $scope->snapshots()->record(SnapshotType::fromString('itpack.state.v1'), ['ok' => true]),
        );

        self::assertInstanceOf(SnapshotInterface::class, $recorded);
        self::assertInstanceOf(RuntimeException::class, $captured);
        self::assertSame('store down', $captured->getMessage());
    }

    public function testShouldDropSkipsPublishingAndPersisting(): void
    {
        $publisher = new RecordingArtifactPublisher();
        $store = new InMemorySnapshotStore();
        $recorder = new DefaultSnapshotRecorder(
            ExecutionId::fromString('exec-1'),
            fn (): null => null,
            new InMemorySequenceAllocator(),
            new DeterministicMonotonicClock(1000, 0),
            $publisher,
            new JsonSnapshotSerializer(),
            new TestSnapshotIdFactory(),
            new TestHashCalculator(),
            $store,
            onStoreFailure: fn (): null => null,
            shouldDrop: fn (): bool => true,
        );

        $snapshot = $recorder->record(SnapshotType::fromString('itpack.state.v1'), ['ok' => true]);

        self::assertCount(0, $publisher->published());
        self::assertCount(0, $store->stored());
        self::assertInstanceOf(SnapshotInterface::class, $snapshot);
    }

    public function testVerifiedActivityExportIdPathBindsActivity(): void
    {
        $publisher = new RecordingArtifactPublisher();
        $activityId = ActivityId::fromString('act-1');
        $recorder = new DefaultSnapshotRecorder(
            ExecutionId::fromString('exec-1'),
            fn (): null => null,
            new InMemorySequenceAllocator(),
            new DeterministicMonotonicClock(1000, 0),
            $publisher,
            new JsonSnapshotSerializer(),
            new TestSnapshotIdFactory(),
            new TestHashCalculator(),
            authority: new ProvenanceAuthority(),
            verifiedActivityExportId: fn (): array => [true, $activityId, 'export-1'],
        );

        $snapshot = $recorder->record(SnapshotType::fromString('itpack.state.v1'), ['ok' => true]);

        self::assertSame($activityId, $snapshot->activityId());
        self::assertCount(1, $publisher->published());
    }

    public function testSerializerFailureReturnsBestEffortSnapshotAndReports(): void
    {
        $captured = null;
        $throwingSerializer = new class () implements SerializerInterface {
            public function normalize(mixed $data): mixed
            {
                throw new RuntimeException('serialize down');
            }

            public function contentType(): string
            {
                return 'application/json';
            }
        };
        $recorder = new DefaultSnapshotRecorder(
            ExecutionId::fromString('exec-1'),
            fn (): null => null,
            new InMemorySequenceAllocator(),
            new DeterministicMonotonicClock(1000, 0),
            new RecordingArtifactPublisher(),
            $throwingSerializer,
            new TestSnapshotIdFactory(),
            new TestHashCalculator(),
            onStoreFailure: static function (Throwable $e) use (&$captured): void {
                $captured = $e;
            },
        );

        $snapshot = $recorder->record(SnapshotType::fromString('itpack.state.v1'), ['ok' => true]);

        self::assertInstanceOf(SnapshotInterface::class, $snapshot);
        self::assertInstanceOf(RuntimeException::class, $captured);
        self::assertSame('serialize down', $captured->getMessage());
        self::assertSame([], $snapshot->payload());
    }

}
