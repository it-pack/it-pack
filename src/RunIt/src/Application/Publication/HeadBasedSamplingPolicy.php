<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use Closure;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionOwnedInterface;
use ItPack\RunIt\Domain\Shared\ArtifactField;

/**
 * Sampling policy that decides once per execution and applies it to every artifact.
 *
 * The first artifact for an execution key consults the wrapped policy; the decision is
 * cached and every later artifact in the same execution follows it. This gives
 * head-based sampling, the parent-child behavior the pipeline needs: either the whole
 * execution is sampled or none of it is, so correlated artifacts stay consistent.
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

    /**
     * @param Closure(ExecutionArtifactInterface): string|null $keyOf
     */
    public function __construct(
        private readonly SamplingPolicyInterface $inner,
        ?Closure $keyOf = null,
    ) {
        $this->keyOf = $keyOf ?? static function (ExecutionArtifactInterface $artifact): string {
            if ($artifact instanceof ExecutionOwnedInterface) {
                return $artifact->executionId()->toString();
            }

            $id = $artifact->normalize()[ArtifactField::ID] ?? '';

            return is_string($id) ? $id : '';
        };
    }

    public function sample(ExecutionArtifactInterface $artifact): bool
    {
        $key = ($this->keyOf)($artifact);

        if (!array_key_exists($key, $this->decisions)) {
            $this->decisions[$key] = $this->inner->sample($artifact);
        }

        return $this->decisions[$key];
    }
}
