<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application;

use ItPack\RunIt\Contract\Domain\AttributeCollectionInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceTypeInterface;
use ItPack\RunIt\Contract\Shared\MediaTypes;

/**
 * Contract for recording occurrences bound to the ambient execution (and activity).
 *
 * The recorder mints identity, fills ownership, timing, and sequence automatically,
 * and returns the occurrence. Configured safety policies may drop it before publication,
 * so the returned object is not proof of publication.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Occurrence
 */
interface OccurrenceRecorderInterface
{
    public function record(
        OccurrenceTypeInterface $type,
        string $source,
        mixed $data = null,
        ?string $subject = null,
        ?AttributeCollectionInterface $extensionAttributes = null,
        ?string $dataSchema = null,
        string $contentType = MediaTypes::APPLICATION_JSON,
    ): OccurrenceInterface;
}
