<?php

declare(strict_types=1);

namespace ItPack\RunIt\Behavior\Domain;

use ItPack\RunIt\Contract\Exception\InvalidAttributeException;

/**
 * The default RunIt attribute rules.
 *
 * An attribute key is a validated string: non-empty, bounded, and made of
 * letters, digits, and the separators `.`, `-`, `_` (so dotted namespacing such
 * as `http.status` is allowed). An attribute value is bounded and recursively
 * primitive: a scalar, null, or a list/map whose members are themselves scalars
 * or null. Objects and resources are rejected so normalization and
 * serialization stay safe and deterministic.
 *
 * The rules are structural: a consumer may supply arbitrary namespaced keys and
 * scalar values. The bounds keep attributes from becoming an unbounded,
 * high-cardinality, or non-serializable data-exfiltration path.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Observation
 */
final class AttributeValidator
{
    /**
     * The maximum accepted attribute key length in bytes.
     */
    public const int MAX_KEY_LENGTH = 256;

    /**
     * The maximum number of attributes in one collection.
     */
    public const int MAX_ATTRIBUTE_COUNT = 64;

    private const string KEY_PATTERN = '/^[A-Za-z0-9._-]++\z/';

    public function assertValidKey(string $key): void
    {
        if ($key === '') {
            throw InvalidAttributeException::emptyKey();
        }

        $length = strlen($key);

        if ($length > self::MAX_KEY_LENGTH) {
            throw InvalidAttributeException::keyTooLong($length, self::MAX_KEY_LENGTH);
        }

        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw InvalidAttributeException::unsupportedKeyCharacters();
        }
    }

    /**
     * Asserts that a collection of attributes is within the bounds and that
     * every value is a supported recursively-primitive type.
     *
     * @param array<string, mixed> $attributes
     */
    public function assertValidAttributes(array $attributes): void
    {
        $count = count($attributes);

        if ($count > self::MAX_ATTRIBUTE_COUNT) {
            throw InvalidAttributeException::tooManyAttributes($count, self::MAX_ATTRIBUTE_COUNT);
        }

        foreach ($attributes as $key => $value) {
            $this->assertValidKey($key);
            $this->assertSupportedValue($value);
        }
    }

    /**
     * @throws InvalidAttributeException when the value is not a scalar, null, or
     *                                   a list/map of scalars
     */
    private function assertSupportedValue(mixed $value): void
    {
        if (is_scalar($value) || $value === null) {
            return;
        }

        if (is_array($value)) {
            foreach ($value as $member) {
                $this->assertSupportedValue($member);
            }

            return;
        }

        throw InvalidAttributeException::unsupportedValue();
    }
}
