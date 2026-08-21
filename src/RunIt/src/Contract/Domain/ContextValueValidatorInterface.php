<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

use ItPack\RunIt\Contract\Exception\InvalidContextValueException;

/**
 * Contract for the validated, bounded context value rules.
 *
 * A context key is a validated string (non-empty, bounded, allowed character
 * set). A context value must be a scalar, null, or a bounded list/map of
 * scalars; objects and resources are rejected so normalization and
 * serialization stay safe and deterministic. Nesting depth is bounded so a
 * deeply nested value cannot recurse until the stack overflows.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#ExecutionContext
 */
interface ContextValueValidatorInterface
{
    /**
     * Asserts that a context key conforms to the validated-key rules.
     *
     * @throws InvalidContextValueException when the key is empty, too long, or uses unsupported characters
     */
    public function assertValidKey(string $key): void;

    /**
     * Asserts that a value is a supported recursively primitive type within the bounds.
     *
     * @param mixed $value
     *
     * @throws InvalidContextValueException when the value is not a scalar, null, or a list/map of scalars, or exceeds a bound
     */
    public function assertSupportedValue(mixed $value): void;
}
