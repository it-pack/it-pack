<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use Closure;
use ItPack\RunIt\Application\Publication\Policy\ExecutionKindSamplePolicy;
use ItPack\RunIt\Contract\Application\Publication\ArtifactFilterInterface;
use ItPack\RunIt\Contract\Application\Publication\ArtifactPredicateInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\ExecutionKindInterface;

/**
 * Filter that accepts artifacts by the kind of their owning execution.
 *
 * Artifacts carry only their execution identity, not the execution kind, so the
 * filter resolves the kind through a caller-supplied closure. The resolution shape
 * is shared with {@see ExecutionKindSamplePolicy} via {@see ExecutionKindPredicate};
 * this filter keeps a kind when it is present in {@see $allowedKinds}.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class ExecutionKindFilter implements ArtifactFilterInterface
{
    private ArtifactPredicateInterface $predicate;

    /**
     * @param Closure(ExecutionIdInterface): ?ExecutionKindInterface $resolver
     * @param list<string>                                           $allowedKinds
     */
    public function __construct(Closure $resolver, array $allowedKinds)
    {
        $this->predicate = new ExecutionKindPredicate(
            $resolver,
            static fn (ExecutionKindInterface $kind): bool => in_array($kind->toString(), $allowedKinds, strict: true),
        );
    }

    public function accepts(ExecutionArtifactInterface $artifact): bool
    {
        return $this->predicate->test($artifact);
    }
}
