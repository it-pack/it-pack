<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for allocating artifact sequences within an execution.
 *
 * Sequences increase monotonically for a given execution and reset per
 * execution: the first artifact in a fresh execution starts at zero. The
 * allocator is a port; the choice of storage (in-memory, persisted, distributed)
 * is an adapter concern, and the domain must not depend on a particular one.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#sequence
 */
interface SequenceAllocatorInterface
{
    /**
     * Allocates and returns the next sequence for the given execution.
     *
     * The first call for an execution returns the initial sequence (zero); each
     * subsequent call returns the next value.
     */
    public function allocate(ExecutionIdInterface $executionId): ArtifactSequenceInterface;
}
