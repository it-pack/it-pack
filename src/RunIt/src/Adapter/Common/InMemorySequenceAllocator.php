<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Common;

use ItPack\RunIt\Contract\Domain\ArtifactSequenceInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\SequenceAllocatorInterface;
use ItPack\RunIt\Domain\Shared\ArtifactSequence;

/**
 * In-memory monotonic sequence allocator.
 *
 * Each execution gets its own counter starting at zero; the first allocation
 * for an execution returns the initial sequence and each subsequent allocation
 * returns the next value. State is held in a plain array, so it is lost when the
 * process ends and must not be used as a durable source of truth across
 * restarts. Durable allocators are adapter concerns.
 *
 * The class is mutable by design: it is a runtime collaborator, not a domain
 * value.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#sequence
 */
final class InMemorySequenceAllocator implements SequenceAllocatorInterface
{
    /**
     * @var array<string, ArtifactSequenceInterface>
     */
    private array $sequences = [];

    public function allocate(ExecutionIdInterface $executionId): ArtifactSequenceInterface
    {
        $key = $executionId->toString();
        $current = $this->sequences[$key] ?? ArtifactSequence::initial();

        $this->sequences[$key] = $current->next();

        return $current;
    }
}
