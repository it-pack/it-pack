<?php

declare(strict_types=1);

namespace ItPack\RunIt\Behavior\Domain;

use ItPack\RunIt\Contract\Exception\InvalidContextValueException;

/**
 * The default RunIt context value rules.
 *
 * A context key is a validated string: non-empty, bounded, and made of
 * letters, digits, and the separators `.`, `-`, `_` (so dotted namespacing such
 * as `tenant.id` is allowed). A context value is bounded and recursively
 * primitive: a scalar, null, or a list/map whose members are themselves scalars
 * or null. Objects and resources are rejected so normalization and
 * serialization stay safe and deterministic.
 *
 * The rules are structural: a consumer may supply arbitrary namespaced keys and
 * scalar values. The bounds keep context from becoming an unbounded,
 * high-cardinality, or non-serializable data-exfiltration path.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#ExecutionContext
 */
final class ContextValueValidator
{
    public const int MAX_KEY_LENGTH = 256;
    public const int MAX_VALUE_LENGTH = 4096;
    public const int MAX_ARRAY_ELEMENTS = 64;

    private const string KEY_PATTERN = '/^[A-Za-z0-9._-]++\z/';

    public function assertValidKey(string $key): void
    {
        if ($key === '') {
            throw InvalidContextValueException::emptyKey();
        }

        $length = strlen($key);

        if ($length > self::MAX_KEY_LENGTH) {
            throw InvalidContextValueException::keyTooLong($length, self::MAX_KEY_LENGTH);
        }

        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw InvalidContextValueException::unsupportedKeyCharacters();
        }
    }

    /**
     * Asserts that a value is a supported recursively-primitive type and within
     * the size bounds.
     *
     * @param mixed $value
     *
     * @throws InvalidContextValueException when the value is not a scalar, null,
     *                                      or a list/map of scalars, or exceeds a bound
     */
    public function assertSupportedValue(mixed $value): void
    {
        if (is_scalar($value) || $value === null) {
            if (is_string($value) && strlen($value) > self::MAX_VALUE_LENGTH) {
                throw InvalidContextValueException::valueTooLarge(strlen($value), self::MAX_VALUE_LENGTH);
            }

            return;
        }

        if (is_array($value)) {
            if (count($value) > self::MAX_ARRAY_ELEMENTS) {
                throw InvalidContextValueException::unsupportedValue();
            }

            foreach ($value as $member) {
                $this->assertSupportedValue($member);
            }

            return;
        }

        throw InvalidContextValueException::unsupportedValue();
    }
}
