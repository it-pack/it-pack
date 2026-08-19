<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use Closure;
use ItPack\RunIt\Contract\Application\Publication\ArtifactFilterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\ExecutionKindInterface;
use ItPack\RunIt\Contract\Domain\ExecutionOwnedInterface;

/**
 * Filter that accepts artifacts by the kind of their owning execution.
 *
 * Artifacts carry only their execution identity, not the execution kind, so the
 * filter resolves the kind through a caller-supplied closure. The resolver is a
 * pure lookup: it must not retain the artifact.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class ExecutionKindFilter implements ArtifactFilterInterface
{
    /**
     * @param Closure(ExecutionIdInterface): ?ExecutionKindInterface $resolver
     * @param list<string>                                           $allowedKinds
     */
    public function __construct(
        private readonly Closure $resolver,
        private readonly array $allowedKinds,
    ) {
    }

    public function accepts(ExecutionArtifactInterface $artifact): bool
    {
        if (!$artifact instanceof ExecutionOwnedInterface) {
            return false;
        }

        $kind = ($this->resolver)($artifact->executionId());

        return $kind instanceof ExecutionKindInterface
            && in_array($kind->toString(), $this->allowedKinds, true);
    }
}
