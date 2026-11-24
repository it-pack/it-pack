<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application\Publication;

/**
 * Contract for classifying verified default-SDK occurrences before sampling.
 *
 * @internal Classifies verified default-SDK occurrences before sampling; not a public contract.
 *
 * @see https://w3id.org/itpack/runit#Occurrence
 */
interface DefaultOccurrenceClassifierInterface
{
    /**
     * @param array<string, mixed> $fields
     */
    public function isNotValid(array $fields): bool;
}
