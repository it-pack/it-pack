<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use Closure;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;

/**
 * Sampling policy that keeps an artifact with a fixed probability.
 *
 * The probability source is injectable so tests can sample deterministically (a
 * closure returning a fixed value) and production can use a real random source.
 * The source must return a float in `[0, 1)`; the artifact is kept when the value
 * is strictly below the ratio.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class RatioSamplePolicy implements SamplingPolicyInterface
{
    private readonly Closure $source;

    /**
     * @param Closure(): float|null $source returns a float in [0, 1); defaults to a real random source
     */
    public function __construct(
        private readonly float $ratio,
        ?Closure $source = null,
    ) {
        $this->source = $source ?? static fn (): float => \lcg_value();
    }

    public function sample(ExecutionArtifactInterface $artifact): bool
    {
        if ($this->ratio >= 1.0) {
            return true;
        }

        if ($this->ratio <= 0.0) {
            return false;
        }

        return ($this->source)() < $this->ratio;
    }
}
