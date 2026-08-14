<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

use Throwable;

/**
 * Contract for mapping a `Throwable` into a safe, immutable description.
 *
 * A mapper reads a thrown error or exception and produces a
 * {@see ThrowableDescriptionInterface} without retaining the original
 * `Throwable`. Implementations decide how much to capture: message length, the
 * stack-trace inclusion policy, the maximum trace length, and the maximum
 * cause depth. Safe normalization is a policy, not a guess, so the policy is
 * supplied to the mapper rather than baked into the description.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#ThrowableDescription
 */
interface ThrowableMapperInterface
{
    /**
     * Maps a throwable into a safe, immutable description.
     *
     * @param Throwable $throwable the error or exception to normalize
     *
     * @return ThrowableDescriptionInterface a throwable-free description
     */
    public function describe(Throwable $throwable): ThrowableDescriptionInterface;
}
