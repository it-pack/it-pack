<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Artifact;

use ItPack\AsyncIt\Application\Artifact\ArtifactVocabulary;
use ItPack\AsyncIt\Application\Artifact\SchedulerLifecycleArtifact;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Domain\TaskId;
use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Shared\Duration;
use ItPack\RunIt\Domain\Shared\Instant;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Application\Artifact\SchedulerLifecycleArtifact
 */
final class SchedulerLifecycleArtifactTest extends TestCase
{
    public function testNormalizeReturnsAllFields(): void
    {
        $taskId = new TaskId('task-123');
        $executionId = ExecutionId::fromString('exec-456');
        $sequence = new InMemorySequenceAllocator()->allocate($executionId);
        $duration = Duration::ofNanoseconds(5_000_000);
        $timestamp = Instant::of(1_700_000_000);

        $artifact = new SchedulerLifecycleArtifact(
            $taskId,
            $executionId,
            ArtifactVocabulary::KIND_TASK_SPAWNED,
            TaskStatus::Succeeded,
            $duration,
            $sequence,
            $timestamp,
        );

        $normalized = $artifact->normalize();

        self::assertSame(ArtifactVocabulary::KIND_TASK_SPAWNED, $normalized['kind']);
        self::assertSame('task-123', $normalized['taskId']);
        self::assertSame('exec-456', $normalized['executionId']);
        self::assertSame(TaskStatus::Succeeded->value, $normalized['status']);
        self::assertSame(5_000_000, $normalized['duration']);
        self::assertSame(0, $normalized['sequence']);
        self::assertSame(1_700_000_000, $normalized['timestamp']);
    }

    public function testNormalizeHandlesNullDurationAndTimestamp(): void
    {
        $taskId = new TaskId('task-null');
        $executionId = ExecutionId::fromString('exec-null');
        $sequence = new InMemorySequenceAllocator()->allocate($executionId);

        $artifact = new SchedulerLifecycleArtifact(
            $taskId,
            $executionId,
            ArtifactVocabulary::KIND_TASK_COMPLETED,
            TaskStatus::Cancelled,
            null,
            $sequence,
            null,
        );

        $normalized = $artifact->normalize();

        self::assertNull($normalized['duration']);
        self::assertSame(0, $normalized['sequence']);
        self::assertSame(0, $normalized['timestamp']);
    }

    public function testNormalizeHandlesNullSequence(): void
    {
        $taskId = new TaskId('task-noseq');
        $executionId = ExecutionId::fromString('exec-noseq');

        $artifact = new SchedulerLifecycleArtifact(
            $taskId,
            $executionId,
            ArtifactVocabulary::KIND_TASK_SPAWNED,
            TaskStatus::Running,
        );

        $normalized = $artifact->normalize();

        self::assertSame(0, $normalized['sequence']);
        self::assertSame(0, $normalized['timestamp']);
        self::assertSame(ArtifactVocabulary::KIND_TASK_SPAWNED, $normalized['kind']);
    }

    public function testExecutionIdReturnsInjectedValue(): void
    {
        $executionId = ExecutionId::fromString('exec-test');
        $artifact = new SchedulerLifecycleArtifact(
            new TaskId('t1'),
            $executionId,
            ArtifactVocabulary::KIND_TASK_SPAWNED,
            TaskStatus::Running,
        );

        self::assertSame($executionId, $artifact->executionId());
    }

    public function testSequenceReturnsSequenceValue(): void
    {
        $taskId = new TaskId('task-1');
        $executionId = ExecutionId::fromString('exec-1');
        $sequence = new InMemorySequenceAllocator()->allocate($executionId);

        $artifact = new SchedulerLifecycleArtifact(
            $taskId,
            $executionId,
            ArtifactVocabulary::KIND_TASK_SPAWNED,
            TaskStatus::Running,
            null,
            $sequence,
        );

        self::assertSame(0, $artifact->sequence());
    }

    public function testSequenceReturnsZeroWhenSequenceIsNull(): void
    {
        $artifact = new SchedulerLifecycleArtifact(
            new TaskId('t1'),
            ExecutionId::fromString('e1'),
            ArtifactVocabulary::KIND_TASK_SPAWNED,
            TaskStatus::Running,
        );

        self::assertSame(0, $artifact->sequence());
    }

    public function testCountDelegatesToSequence(): void
    {
        $taskId = new TaskId('task-1');
        $executionId = ExecutionId::fromString('exec-1');
        $sequence = new InMemorySequenceAllocator()->allocate($executionId);

        $artifact = new SchedulerLifecycleArtifact(
            $taskId,
            $executionId,
            ArtifactVocabulary::KIND_TASK_SPAWNED,
            TaskStatus::Running,
            null,
            $sequence,
        );

        self::assertSame(0, $artifact->count());
    }

    public function testTimestampReturnsEpochSeconds(): void
    {
        $timestamp = Instant::of(1_700_000_000);
        $artifact = new SchedulerLifecycleArtifact(
            new TaskId('t1'),
            ExecutionId::fromString('e1'),
            ArtifactVocabulary::KIND_TASK_SPAWNED,
            TaskStatus::Running,
            null,
            null,
            $timestamp,
        );

        self::assertSame(1_700_000_000, $artifact->timestamp());
    }

    public function testTimestampReturnsZeroWhenNull(): void
    {
        $artifact = new SchedulerLifecycleArtifact(
            new TaskId('t1'),
            ExecutionId::fromString('e1'),
            ArtifactVocabulary::KIND_TASK_SPAWNED,
            TaskStatus::Running,
        );

        self::assertSame(0, $artifact->timestamp());
    }
}
