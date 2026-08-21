<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Application\Publication\ArtifactFilterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;

/**
 * Filter that combines several filters by boolean rule.
 *
 * In `requireAll` mode (the default) every filter must accept the artifact; in
 * `requireAny` mode a single accepting filter is enough. Combining filters lets a
 * pipeline express "observations of kind X at warning level or above" without a
 * bespoke predicate class.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class CompositeArtifactFilter implements ArtifactFilterInterface
{
    /**
     * @param list<ArtifactFilterInterface> $filters
     */
    public function __construct(
        private array $filters,
        private bool  $requireAll = true,
    ) {
    }

    public function accepts(ExecutionArtifactInterface $artifact): bool
    {
        if ($this->filters === []) {
            return true;
        }

        foreach ($this->filters as $filter) {
            $accepted = $filter->accepts($artifact);

            if ($this->requireAll && !$accepted) {
                return false;
            }

            if (!$this->requireAll && $accepted) {
                return true;
            }
        }

        return $this->requireAll;
    }
}
