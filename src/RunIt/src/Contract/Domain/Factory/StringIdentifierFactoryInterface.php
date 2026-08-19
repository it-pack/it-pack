<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain\Factory;

use ItPack\RunIt\Common\Contract\FactoryInterface;
use ItPack\RunIt\Contract\Domain\StringIdentifierInterface;
use ItPack\RunIt\Contract\Exception\InvalidValueExceptionInterface;

/**
 * Contract for a factory that mints identifiers of one concrete type.
 *
 * A factory binds an identifier type to a generation policy so that callers
 * requesting a new execution, activity, observation, occurrence, snapshot, or
 * correlation identity never choose a randomness source themselves.
 *
 * Reconstructing a known identifier does not require a factory: every
 * identifier value object exposes a `fromString()` named constructor.
 *
 * @template-covariant TIdentifier of StringIdentifierInterface
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#executionId
 */
interface StringIdentifierFactoryInterface extends FactoryInterface
{
    /**
     * Returns a new identifier produced by the configured generation policy.
     *
     * @return TIdentifier
     *
     * @throws InvalidValueExceptionInterface when the generator produced a
     *                                        value the identifier rejects
     */
    public function build(): StringIdentifierInterface;
}
