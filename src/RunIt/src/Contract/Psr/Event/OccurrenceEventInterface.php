<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Psr\Event;

/**
 * Contract for a PSR-14 event that carries a RunIt occurrence.
 *
 * @see https://w3id.org/itpack/runit#Occurrence
 */
interface OccurrenceEventInterface
{
    public function stopPropagation(): void;
}
