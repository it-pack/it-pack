<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication\Policy;

use Closure;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionOwnedInterface;
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
        $this->keyOf = $keyOf ?? static function (ExecutionArtifactInterface $artifact): string {
            if ($artifact instanceof ExecutionOwnedInterface) {
                return $artifact->executionId()->toString();
            }

            $id = $artifact->normalize()[ArtifactField::ID] ?? '';

            return is_string($id) ? $id : '';
        };
        $this->maxTrackedExecutions = ($limits ?? SecurityLimits::default())->maxTrackedExecutions;
    }

    public function sample(ExecutionArtifactInterface $artifact): bool
    {
        $key = ($this->keyOf)($artifact);

        if (!array_key_exists($key, $this->decisions)) {
            if (count($this->decisions) >= $this->maxTrackedExecutions) {
                $oldest = array_key_first($this->decisions);

                if (is_string($oldest)) {
                    unset($this->decisions[$oldest]);
                }
            }

            $this->decisions[$key] = $this->inner->sample($artifact);
        }

        return $this->decisions[$key];
    }
}
