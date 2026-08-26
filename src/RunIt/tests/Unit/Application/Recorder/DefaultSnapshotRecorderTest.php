<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Recorder;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Memory\InMemorySnapshotStore;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Port\SnapshotStoreInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Snapshot\SnapshotType;
use RuntimeException;
use Tests\Support\Fixture\RunItTestKit;
use Throwable;

/**
 * @group application
 * @group recorder
 */
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
        $result = [];
        foreach ($this->kit->artifacts() as $artifact) {
            if ($artifact instanceof SnapshotInterface) {
                $result[] = $artifact;
            }
        }

        return $result;
    }

    public function testCheckpointSnapshotIsPublishedAndBoundToExecution(): void
    {
        $this->kit->runner->run(
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            function ($scope) {
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
        $kit = new RunItTestKit(publisher: null, snapshotStore: $store);

        $kit->runner->run(
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            function ($scope) {
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
            publisher: null,
            snapshotStore: $throwingStore,
            onSnapshotStoreFailure: static function (Throwable $error) use (&$captured): void {
                $captured = $error;
            },
        );

        $recorded = $kit->runner->run(
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            function ($scope) {
                return $scope->snapshots()->record(SnapshotType::fromString('itpack.state.v1'), ['ok' => true]);
            },
        );

        self::assertInstanceOf(SnapshotInterface::class, $recorded);
        self::assertInstanceOf(RuntimeException::class, $captured);
        self::assertSame('store down', $captured->getMessage());
    }
}
