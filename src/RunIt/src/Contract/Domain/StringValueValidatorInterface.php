<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

use ItPack\RunIt\Contract\Exception\InvalidValueExceptionInterface;

/**
 * Contract for the rules a string value (name, kind, occurrence type, or
 * snapshot type) must satisfy.
 *
 * Value objects validate themselves on construction using the default rules.
 * This contract exists so a consumer can apply stricter rules (for example,
 * requiring a versioned occurrence type) at the boundary where untrusted
 * values enter the system, without weakening the domain guarantee.
 *
 * A validator either accepts a value silently or rejects it by throwing. It
 * never returns a repaired, trimmed, or transcoded value.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#executionKind
 */
interface StringValueValidatorInterface
{
    /**
     * Validates a raw value.
     *
     * @param string $value the untrusted candidate value
     *
     * @throws InvalidValueExceptionInterface when the value violates a rule
     */
    public function validate(string $value): void;
}
