<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Adapter\Psr\Event;

use ItPack\RunIt\Contract\Domain\OccurrenceInterface;

/**
 * Contract for wrapping a RunIt occurrence in a PSR-14 event.
 *
 * @see https://w3id.org/itpack/runit#Occurrence
 */
interface OccurrenceEventWrapperInterface
{
    public function wrap(OccurrenceInterface $occurrence): OccurrenceEventInterface;
}
