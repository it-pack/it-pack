<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use ItPack\RunIt\Contract\Domain\ArtifactSequenceInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\SequenceAllocatorInterface;
use RuntimeException;

/**
 * Sequence allocator that always throws, for exercising allocation-failure handling.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class ThrowingSequenceAllocator implements SequenceAllocatorInterface
{
    public function allocate(ExecutionIdInterface $executionId): ArtifactSequenceInterface
    {
        throw new RuntimeException('sequence allocation is broken');
    }
}
