<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Psr\Event;

use ItPack\RunIt\Contract\Domain\OccurrenceInterface;

/**
 * Maps an arbitrary PSR-14 event object to a RunIt occurrence.
 *
 * The mapper is configurable: the occurrence type defaults to a configured value
 * but can be overridden per call, the bound execution is injected, an identifier
 * generator mints the occurrence identity, and a data extractor controls what of
 * the event becomes the occurrence payload.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Occurrence
 */
interface PsrEventToOccurrenceMapperInterface
{
    /**
     * Maps a PSR-14 event object to a RunIt occurrence.
     *
     * @param object $event the dispatched PSR-14 event
     * @param string|null $type optional overriding occurrence type
     */
    public function map(object $event, ?string $type = null): OccurrenceInterface;
}
