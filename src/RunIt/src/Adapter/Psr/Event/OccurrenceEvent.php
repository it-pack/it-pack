<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Psr\Event;

use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use Psr\EventDispatcher\StoppableEventInterface;

/**
 * A PSR-14 event that carries a RunIt occurrence.
 *
 * The occurrence itself is immutable; only the propagation-stopped flag is
 * mutable, as PSR-14 expects listeners to be able to stop propagation. Wrapping an
 * occurrence (rather than extending it) keeps the domain type unchanged.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Occurrence
 */
final class OccurrenceEvent implements StoppableEventInterface
{
    private bool $propagationStopped = false;

    public function __construct(public readonly OccurrenceInterface $occurrence)
    {
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
}
