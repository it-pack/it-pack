<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use Closure;
use ItPack\RunIt\Contract\Application\Publication\ArtifactPredicateInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\ExecutionKindInterface;
use ItPack\RunIt\Contract\Domain\ExecutionOwnedInterface;

/**
 * Shared execution-kind predicate.
 *
 * The execution-kind filter and the execution-kind sampling policy share the same
 * resolution shape (an artifact has only an execution identity, so the kind is
 * resolved through a caller-supplied closure), differing only in how a resolved kind
 * is judged. Both callers build the {@see $decide} closure from their own decision
 * representation, so the resolution logic lives here once.
 *
 * @internal
 *
 * @see https://w3id.org/itpack/runit#executionKind
 */
final readonly class ExecutionKindPredicate implements ArtifactPredicateInterface
{
    /**
     * @param Closure(ExecutionIdInterface): ?ExecutionKindInterface $resolver
     * @param Closure(ExecutionKindInterface): bool                  $decide
     */
    public function __construct(
        private Closure $resolver,
        private Closure $decide,
    ) {
    }

    public function test(ExecutionArtifactInterface $artifact): bool
    {
        if (!$artifact instanceof ExecutionOwnedInterface) {
            return false;
        }

        $kind = ($this->resolver)($artifact->executionId());

        if (!$kind instanceof ExecutionKindInterface) {
            return false;
        }

        return ($this->decide)($kind);
    }
}
