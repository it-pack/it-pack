<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use Closure;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\ExecutionKindInterface;
use ItPack\RunIt\Contract\Domain\ExecutionOwnedInterface;

/**
 * Sampling policy that keeps artifacts by the kind of their owning execution.
 *
 * Artifacts carry only execution identity, so the policy resolves the kind through a
 * caller-supplied closure. When the kind is unknown or absent from the decision map the
 * artifact is dropped (fail-closed on the decision), so an unconfigured kind never leaks.
 *
 * @see https://w3id.org/itpack/runit#executionKind
 */
final readonly class ExecutionKindSamplePolicy implements SamplingPolicyInterface
{
    /**
     * @param Closure(ExecutionIdInterface): ?ExecutionKindInterface $resolver
     * @param array<string, bool>                                     $decisions kind name => keep?
     */
    public function __construct(
        private Closure $resolver,
        private array   $decisions,
    ) {
    }

    public function sample(ExecutionArtifactInterface $artifact): bool
    {
        if (!$artifact instanceof ExecutionOwnedInterface) {
            return false;
        }

        $kind = ($this->resolver)($artifact->executionId());

        if (!$kind instanceof ExecutionKindInterface) {
            return false;
        }

        return $this->decisions[$kind->toString()] ?? false;
    }
}
