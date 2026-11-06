<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Application\Publication\ArtifactFilterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;

/**
 * Filter that accepts artifacts by their concrete type or implemented contract.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class ArtifactTypeFilter implements ArtifactFilterInterface
{
    /**
     * @param list<class-string<ExecutionArtifactInterface>> $types
     */
    public function __construct(
        private array $types,
    ) {
    }

    public function accepts(ExecutionArtifactInterface $artifact): bool
    {
        return array_any($this->types, fn (string $type): bool => $artifact instanceof $type);
    }
}
