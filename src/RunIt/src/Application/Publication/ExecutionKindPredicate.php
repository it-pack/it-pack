<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use Closure;
use ItPack\RunIt\Contract\Application\Publication\ArtifactPredicateInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\ExecutionKindInterface;
use ItPack\RunIt\Contract\Domain\ExecutionOwnedInterface;
use ItPack\RunIt\Contract\Domain\RedactedArtifactInterface;
use ItPack\RunIt\Contract\Domain\TrustedArtifactProjectionInterface;
use ItPack\RunIt\Domain\Exception\InvalidIdentifierException;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Shared\ArtifactField;

/**
 * Shared execution-kind predicate.
 *
 * The execution-kind filter and the execution-kind sampling policy share the same
 * resolution shape (an artifact has only an execution identity, so the kind is
 * resolved through a caller-supplied closure), differing only in how a resolved kind
 * is judged. Both callers build the {@see $decide} closure from their own decision
 * representation, so the resolution logic lives here once.
 *
 * Artifacts implementing {@see ExecutionOwnedInterface} are resolved through their
 * `executionId()` method. Trusted projections and all {@see RedactedArtifactInterface}
 * instances are resolved through their normalized `id` for executions or `execution_id`
 * for other artifacts, which user-supplied redacted artifacts can control. Missing or
 * invalid IDs and unresolved kinds do not match.
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
        if ($artifact instanceof ExecutionOwnedInterface) {
            try {
                $kind = ($this->resolver)($artifact->executionId());
            } catch (InvalidIdentifierException) {
                return false;
            }

            if (!$kind instanceof ExecutionKindInterface) {
                return false;
            }

            return ($this->decide)($kind);
        }

        if ($artifact instanceof TrustedArtifactProjectionInterface
            || $artifact instanceof VerifiedArtifactProjection) {
            return $this->resolveKindFromNormalized($artifact);
        }

        if ($artifact instanceof RedactedArtifactInterface) {
            return $this->resolveKindFromNormalized($artifact);
        }

        return false;
    }

    private function resolveKindFromNormalized(ExecutionArtifactInterface $artifact): bool
    {
        $normalized = $artifact->normalize();
        $isExecution = ($artifact instanceof RedactedArtifactInterface
            || $artifact instanceof VerifiedArtifactProjection)
            && $artifact->artifactType() === 'execution';
        $executionId = $normalized[$isExecution ? ArtifactField::ID : ArtifactField::EXECUTION_ID] ?? null;
        if (!is_string($executionId)) {
            return false;
        }

        try {
            $kind = ($this->resolver)(ExecutionId::fromString($executionId));
        } catch (InvalidIdentifierException) {
            return false;
        }

        if (!$kind instanceof ExecutionKindInterface) {
            return false;
        }

        return ($this->decide)($kind);
    }
}
