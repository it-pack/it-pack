<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

use ItPack\RunIt\Contract\Exception\InvalidValueExceptionInterface;

/**
 * Contract for the rules a string identifier value must satisfy.
 *
 * Identifier value objects validate themselves on construction using the
 * default rules. This contract exists so a consumer can apply stricter rules
 * (for example, requiring a UUID) at the boundary where untrusted identifier
 * values enter the system, without weakening the guarantees of the domain.
 *
 * A validator either accepts a value silently or rejects it by throwing.
 * It never returns a repaired, trimmed, or transcoded value: two distinct
 * inputs must never collapse into one identifier.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#executionId
 */
interface StringIdentifierValidatorInterface
{
    /**
     * Validates a raw identifier value.
     *
     * @param string $value the untrusted candidate value
     *
     * @throws InvalidValueExceptionInterface when the value violates a rule
     */
    public function validate(string $value): void;
}
