<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Application\Publication\ArtifactFilterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;

/**
 * Filter that accepts occurrences by their stable occurrence type.
 *
 * Non-occurrence artifacts always pass, so a type allow-list scopes only
 * occurrence routing and never suppresses other artifact kinds.
 *
 * @see https://w3id.org/itpack/runit#occurrenceType
 */
final class OccurrenceTypeFilter implements ArtifactFilterInterface
{
    /**
     * @param list<string> $types
     */
    public function __construct(
        private readonly array $types,
    ) {
    }

    public function accepts(ExecutionArtifactInterface $artifact): bool
    {
        if (!$artifact instanceof OccurrenceInterface) {
            return false;
        }

        return in_array($artifact->type()->toString(), $this->types, true);
    }
}
