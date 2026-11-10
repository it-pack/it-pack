<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use Closure;
use ItPack\RunIt\Contract\Application\Publication\ArtifactPredicateInterface;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\ExecutionKindInterface;

/**
 * Sampling policy that keeps artifacts by the kind of their owning execution.
 *
 * Artifacts carry only execution identity, so the policy resolves the kind through a
 * caller-supplied closure. The resolution shape is shared with {@see ExecutionKindFilter}
 * via {@see ExecutionKindPredicate}; this policy keeps a kind only when its entry in
 * {@see $decisions} is true, so an unconfigured kind never leaks (fail-closed).
 *
 * @see https://w3id.org/itpack/runit#executionKind
 */
final readonly class ExecutionKindSamplePolicy implements SamplingPolicyInterface
{
    private ArtifactPredicateInterface $predicate;

    /**
     * @param Closure(ExecutionIdInterface): ?ExecutionKindInterface $resolver
     * @param array<string, bool>                                     $decisions kind name => keep?
     */
    public function __construct(Closure $resolver, array $decisions)
    {
        $this->predicate = new ExecutionKindPredicate(
            $resolver,
            static fn (ExecutionKindInterface $kind): bool => $decisions[$kind->toString()] ?? false,
        );
    }

    public function sample(ExecutionArtifactInterface $artifact): bool
    {
        return $this->predicate->test($artifact);
    }
}
