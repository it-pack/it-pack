<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use Closure;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\ArtifactType;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionOwnedInterface;
use ItPack\RunIt\Contract\Domain\RedactedArtifactInterface;
use ItPack\RunIt\Contract\Domain\SecurityLimitsInterface;
use ItPack\RunIt\Domain\Shared\ArtifactField;
use ItPack\RunIt\Domain\Shared\SecurityLimits;

/**
 * Sampling policy that decides once per execution and applies it to every artifact.
 *
 * The first artifact for an execution key consults the wrapped policy; the decision is
 * cached and every later artifact in the same execution follows it. This gives
 * head-based sampling, the parent-child behavior the pipeline needs: either the whole
 * execution is sampled or none of it is, so correlated artifacts stay consistent.
 *
 * The execution key comes from {@see ExecutionOwnedInterface::executionId()}
 * when available. Otherwise, concrete verified and classified projections and
 * any {@see RedactedArtifactInterface} use their normalized execution_id, or id
 * for executions. These types can be constructed by callers; their identity is
 * not proof of provenance, and a supplied identity can occupy another execution's
 * cache entry. Caller implementations of the redacted or trusted projection
 * interfaces are handled the same way; neither interface proves provenance.
 *
 * Artifacts without a usable execution identity are sampled independently and
 * do not occupy the cache. No empty fallback key is shared across unrelated
 * artifacts.
 *
 * The decision cache is bounded: when the bound from {@see SecurityLimits::maxTrackedExecutions}
 * is exceeded, the oldest key is evicted (deterministic insertion order). A trimmed key may
 * be re-decided if its execution emits later artifacts — a consistency trade-off accepted
 * in exchange for a cache that cannot grow without limit inside persistent workers.
 *
 * The execution key defaults to the artifact's execution identity but is injectable for
 * callers that group by a correlation id instead.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class HeadBasedSamplingPolicy implements SamplingPolicyInterface
{
    /**
     * @var array<string, bool>
     */
    private array $decisions = [];

    private readonly Closure $keyOf;

    private readonly int $maxTrackedExecutions;

    /**
     * @param Closure(ExecutionArtifactInterface): string|null $keyOf
     */
    public function __construct(
        private readonly SamplingPolicyInterface $inner,
        ?Closure $keyOf = null,
        ?SecurityLimitsInterface $limits = null,
    ) {
        $this->keyOf = $keyOf ?? $this->resolveKey(...);
        $this->maxTrackedExecutions = ($limits ?? SecurityLimits::default())->maxTrackedExecutions;
    }

    public function sample(ExecutionArtifactInterface $artifact): bool
    {
        $key = ($this->keyOf)($artifact);

        if ($key === null || $key === '') {
            return $this->inner->sample($artifact);
        }

        if (!array_key_exists($key, $this->decisions)) {
            if (count($this->decisions) >= $this->maxTrackedExecutions) {
                $oldest = array_key_first($this->decisions);

                if ($oldest !== null) {
                    unset($this->decisions[$oldest]);
                }
            }

            $this->decisions[$key] = $this->inner->sample($artifact);
        }

        return $this->decisions[$key];
    }

    private function resolveKey(ExecutionArtifactInterface $artifact): ?string
    {
        if ($artifact instanceof ExecutionOwnedInterface) {
            return $artifact->executionId()->toString();
        }

        if ($artifact instanceof VerifiedArtifactProjection
            || $artifact instanceof RedactedArtifactInterface) {
            return $this->executionIdentityFromNormalized($artifact->normalize(), $artifact->artifactType());
        }

        return null;
    }

    /**
     * @param array<string, mixed> $normalized
     */
    private function executionIdentityFromNormalized(array $normalized, string $artifactType): ?string
    {
        $key = $artifactType === ArtifactType::EXECUTION ? ArtifactField::ID : ArtifactField::EXECUTION_ID;
        $id = $normalized[$key] ?? null;

        return is_string($id) ? $id : null;
    }
}
