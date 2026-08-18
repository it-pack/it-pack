<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application;

use ItPack\RunIt\Contract\Domain\AttributeCollectionInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceTypeInterface;

/**
 * Contract for recording occurrences bound to the ambient execution (and activity).
 *
 * The recorder mints identity, fills ownership, timing, and sequence automatically,
 * and publishes the occurrence before returning it.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Occurrence
 */
interface OccurrenceRecorderInterface
{
    /**
     * @param mixed $data
     */
    public function record(
        OccurrenceTypeInterface $type,
        string $source,
        mixed $data = null,
        ?string $subject = null,
        ?AttributeCollectionInterface $extensionAttributes = null,
        ?string $dataSchema = null,
        string $contentType = 'application/json',
    ): OccurrenceInterface;
}
