<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Snapshot;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Exception\InvalidContextValueException;
use ItPack\RunIt\Domain\Activity\ActivityId;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Shared\DeterministicHashCalculator;
use ItPack\RunIt\Domain\Shared\Instant;
use ItPack\RunIt\Domain\Snapshot\ExecutionSnapshot;
use ItPack\RunIt\Domain\Snapshot\Snapshot;
use ItPack\RunIt\Domain\Snapshot\SnapshotClassification;
use ItPack\RunIt\Domain\Snapshot\SnapshotId;
use ItPack\RunIt\Domain\Snapshot\SnapshotType;

final class SnapshotTest extends Unit
{
    private function calculator(): DeterministicHashCalculator
    {
        return new DeterministicHashCalculator();
    }

    public function testCheckpointSnapshotComputesStableHash(): void
    {
        $snapshot = Snapshot::create(
            SnapshotId::fromString('snap-1'),
            ExecutionId::fromString('exec-1'),
            SnapshotType::fromString('execution.snapshot'),
            1,
            ['status' => 'running', 'count' => 3],
            $this->calculator(),
            null,
            null,
            SnapshotClassification::Checkpoint,
            Instant::of(1000),
        );

        self::assertFalse($snapshot->classification()->isTerminal());
        self::assertSame(1, $snapshot->schemaVersion());
        self::assertSame('execution.snapshot', $snapshot->type()->toString());
        self::assertSame(1000, $snapshot->timestamp());
        // identical payload yields identical hash
        self::assertSame(
            $snapshot->contentHash(),
            Snapshot::create(
                SnapshotId::fromString('snap-2'),
                ExecutionId::fromString('exec-1'),
                SnapshotType::fromString('execution.snapshot'),
                1,
                ['status' => 'running', 'count' => 3],
                $this->calculator(),
                null,
                null,
                SnapshotClassification::Checkpoint,
                Instant::of(2000),
            )->contentHash(),
        );
    }

    public function testTerminalSnapshotIsMarkedTerminal(): void
    {
        $snapshot = Snapshot::create(
            SnapshotId::fromString('snap-t'),
            ExecutionId::fromString('exec-1'),
            SnapshotType::fromString('execution.snapshot'),
            1,
            ['status' => 'succeeded'],
            $this->calculator(),
            null,
            null,
            SnapshotClassification::Terminal,
            Instant::of(1000),
        );

        self::assertTrue($snapshot->classification()->isTerminal());
    }

    public function testPredecessorChainIsCaptured(): void
    {
        $snapshot = Snapshot::create(
            SnapshotId::fromString('snap-2'),
            ExecutionId::fromString('exec-1'),
            SnapshotType::fromString('execution.snapshot'),
            1,
            ['step' => 2],
            $this->calculator(),
            null,
            SnapshotId::fromString('snap-1'),
        );

        self::assertSame('snap-1', $snapshot->predecessorId()?->toString());
    }

    public function testExecutionSnapshotIsSnapshot(): void
    {
        $snapshot = ExecutionSnapshot::create(
            SnapshotId::fromString('snap-e'),
            ExecutionId::fromString('exec-1'),
            SnapshotType::fromString('execution.snapshot'),
            1,
            ['status' => 'succeeded'],
            $this->calculator(),
            ActivityId::fromString('act-1'),
        );

        self::assertInstanceOf(ExecutionSnapshot::class, $snapshot);
        self::assertSame('act-1', $snapshot->activityId()?->toString());
    }

    public function testNormalizationIsDeterministic(): void
    {
        $snapshot = Snapshot::create(
            SnapshotId::fromString('snap-1'),
            ExecutionId::fromString('exec-1'),
            SnapshotType::fromString('execution.snapshot'),
            1,
            ['count' => 3],
            $this->calculator(),
            null,
            null,
            SnapshotClassification::Checkpoint,
            Instant::of(1000),
        );

        $normalized = $snapshot->normalize();

        self::assertArrayHasKey('content_hash', $normalized);
        self::assertArrayHasKey('payload', $normalized);
        self::assertSame(['count' => 3], $normalized['payload']);
        self::assertSame($snapshot->normalize(), $snapshot->normalize());
    }

    public function testExecutionSnapshotRejectsNonPrimitivePayload(): void
    {
        $this->expectException(InvalidContextValueException::class);

        ExecutionSnapshot::create(
            SnapshotId::fromString('snap-bad'),
            ExecutionId::fromString('exec-1'),
            SnapshotType::fromString('execution.snapshot'),
            1,
            ['obj' => new \stdClass()],
            $this->calculator(),
            ActivityId::fromString('act-1'),
        );
    }

    public function testExecutionSnapshotAcceptsPrimitivePayload(): void
    {
        $snapshot = ExecutionSnapshot::create(
            SnapshotId::fromString('snap-ok'),
            ExecutionId::fromString('exec-1'),
            SnapshotType::fromString('execution.snapshot'),
            1,
            ['status' => 'succeeded', 'count' => 3, 'ratio' => 0.5, 'flag' => true, 'note' => null],
            $this->calculator(),
            ActivityId::fromString('act-1'),
        );

        self::assertInstanceOf(ExecutionSnapshot::class, $snapshot);
        self::assertSame(
            ['status' => 'succeeded', 'count' => 3, 'ratio' => 0.5, 'flag' => true, 'note' => null],
            $snapshot->payload(),
        );
    }
}
