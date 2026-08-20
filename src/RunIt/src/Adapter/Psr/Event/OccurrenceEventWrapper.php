<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Psr\Event;

use ItPack\RunIt\Contract\Domain\OccurrenceInterface;

/**
 * Wraps a RunIt occurrence in a PSR-14 event.
 *
 * The wrapper preserves the occurrence immutability: it returns the same
 * occurrence instance and never alters it. Listeners may only stop propagation,
 * which is recorded on the event wrapper, not on the occurrence.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Occurrence
 */
final class OccurrenceEventWrapper
{
    public function wrap(OccurrenceInterface $occurrence): OccurrenceEvent
    {
        return new OccurrenceEvent($occurrence);
    }
}
