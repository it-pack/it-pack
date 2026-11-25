<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application\Publication;

use ItPack\RunIt\Contract\Domain\OccurrenceInterface;

/**
 * Contract for reconstructing occurrences from classified values.
 *
 * @internal shared reconstruction machinery; not a public contract.
 *
 * @see https://w3id.org/itpack/runit#Occurrence
 */
interface ClassifiedOccurrenceFactoryInterface
{
    /**
     * @param array<string, mixed> $fields
     */
    public function create(array $fields): ?OccurrenceInterface;
}
