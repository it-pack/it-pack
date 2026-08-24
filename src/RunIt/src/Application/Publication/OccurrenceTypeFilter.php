<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Application\Publication\ArtifactFilterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;

/**
 * Filter that scopes a route to occurrences of specific stable occurrence types.
 *
 * Only occurrences whose type is in the configured list are accepted; every
 * other artifact kind — including other occurrences — is suppressed on the
 * route. This makes the filter the tool for type-scoped routing (for example,
 * a route dedicated to `checkout.completed.v1`).
 *
 * @see https://w3id.org/itpack/runit#occurrenceType
 */
final readonly class OccurrenceTypeFilter implements ArtifactFilterInterface
{
    /**
     * @param list<string> $types
     */
    public function __construct(
        private array $types,
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
