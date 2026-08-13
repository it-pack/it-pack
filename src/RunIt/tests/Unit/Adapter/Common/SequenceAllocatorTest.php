<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Common;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Shared\ArtifactSequence;

final class SequenceAllocatorTest extends Unit
{
    public function testFirstAllocationForAnExecutionReturnsTheInitialSequence(): void
    {
        $allocator = new InMemorySequenceAllocator();
        $executionId = ExecutionId::fromString('exec-1');

        $sequence = $allocator->allocate($executionId);

        self::assertInstanceOf(ArtifactSequence::class, $sequence);
        self::assertSame(0, $sequence->value());
    }

    public function testAllocationsIncreaseMonotonicallyWithinAnExecution(): void
    {
        $allocator = new InMemorySequenceAllocator();
        $executionId = ExecutionId::fromString('exec-1');

        $first = $allocator->allocate($executionId);
        $second = $allocator->allocate($executionId);
        $third = $allocator->allocate($executionId);

        self::assertSame(0, $first->value());
        self::assertSame(1, $second->value());
        self::assertSame(2, $third->value());
        self::assertSame(2, $second->next()->value());
    }

    public function testSequencesResetPerExecution(): void
    {
        $allocator = new InMemorySequenceAllocator();

        $first = $allocator->allocate(ExecutionId::fromString('exec-a'));
        $second = $allocator->allocate(ExecutionId::fromString('exec-a'));
        $other = $allocator->allocate(ExecutionId::fromString('exec-b'));

        self::assertSame(1, $second->value());
        self::assertSame(0, $other->value());
        self::assertNotSame($second->value(), $other->value());
    }

    public function testAllocationIsIndependentOfIdentifierType(): void
    {
        $allocator = new InMemorySequenceAllocator();

        $sequence = $allocator->allocate(ExecutionId::fromString('exec-1'));

        self::assertInstanceOf(ExecutionIdInterface::class, ExecutionId::fromString('exec-1'));
        self::assertSame(0, $sequence->value());
    }
}
