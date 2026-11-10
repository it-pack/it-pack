<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use Closure;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use Random\IntervalBoundary;
use Random\Randomizer;

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
final readonly class RatioSamplePolicy implements SamplingPolicyInterface
{
    private Closure $source;

    /**
     * @param Closure(): float|null $source returns a float in [0, 1); defaults to a real random source
     */
    public function __construct(
        private float $ratio,
        ?Closure      $source = null,
    ) {
        // lcg_value has been DEPRECATED as of PHP 8.4.0.
        $this->source = $source ?? static fn (): float => new Randomizer()->getFloat(
            0,
            1,
            IntervalBoundary::ClosedOpen
        );
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
