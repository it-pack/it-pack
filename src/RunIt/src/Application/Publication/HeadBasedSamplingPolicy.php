<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use Closure;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionOwnedInterface;
use ItPack\RunIt\Contract\Domain\RedactedArtifactInterface;
use ItPack\RunIt\Contract\Domain\TrustedArtifactProjectionInterface;
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
 * when available. Otherwise it is derived from the trusted execution_id field
 * of {@see TrustedArtifactProjectionInterface} instances, and from the
 * execution_id field (or id for executions) of any {@see RedactedArtifactInterface} instance as a
 * fallback. User-supplied redacted artifacts that report another execution's
 * execution_id can therefore occupy that execution's decision cache. This is
 * the documented trust boundary; restricting the redacted branch to SDK-produced
 * types is a separate decision.
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
        ?SecurityLimits $limits = null,
    ) {
        $this->keyOf = $keyOf ?? static function (ExecutionArtifactInterface $artifact): ?string {
            if ($artifact instanceof ExecutionOwnedInterface) {
                return $artifact->executionId()->toString();
            }

            // SDK-classified projections carry a trustworthy execution_id in
            // their normalized projection.
            if ($artifact instanceof TrustedArtifactProjectionInterface
                || $artifact instanceof VerifiedArtifactProjection) {
                $normalized = $artifact->normalize();
                $id = $normalized[$artifact->artifactType() === 'execution'
                    ? ArtifactField::ID : ArtifactField::EXECUTION_ID] ?? null;

                if (is_string($id)) {
                    return $id;
                }
            }

            if ($artifact instanceof RedactedArtifactInterface) {
                $normalized = $artifact->normalize();
                $id = $normalized[$artifact->artifactType() === 'execution'
                    ? ArtifactField::ID : ArtifactField::EXECUTION_ID] ?? null;

                return is_string($id) ? $id : null;
            }

            return null;
        };
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
}
