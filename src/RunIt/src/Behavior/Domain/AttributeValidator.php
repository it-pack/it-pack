<?php

declare(strict_types=1);

namespace ItPack\RunIt\Behavior\Domain;

use ItPack\RunIt\Contract\Exception\InvalidAttributeException;
use ItPack\RunIt\Domain\Shared\SecurityLimits;

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
 * high-cardinality, or non-serializable data-exfiltration path. Limits can be
 * tightened through a {@see SecurityLimits} instance; when none is supplied the
 * conservative built-in defaults apply.
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

    /**
     * The maximum accepted scalar attribute value length in bytes.
     */
    public const int MAX_VALUE_BYTES = 4096;

    /**
     * The maximum nesting depth of an attribute collection value.
     */
    public const int MAX_DEPTH = 8;

    private const string KEY_PATTERN = '/^[A-Za-z0-9._-]++\z/';

    public function __construct(
        private readonly ?SecurityLimits $limits = null,
    ) {
    }

    public function assertValidKey(string $key): void
    {
        if ($key === '') {
            throw InvalidAttributeException::emptyKey();
        }

        $maxKey = $this->limits === null ? self::MAX_KEY_LENGTH : $this->limits->maxAttributeKeyBytes;
        $length = strlen($key);

        if ($length > $maxKey) {
            throw InvalidAttributeException::keyTooLong($length, $maxKey);
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
        $maxCount = $this->limits === null ? self::MAX_ATTRIBUTE_COUNT : $this->limits->maxAttributeCount;
        $count = count($attributes);

        if ($count > $maxCount) {
            throw InvalidAttributeException::tooManyAttributes($count, $maxCount);
        }

        $maxDepth = $this->limits === null ? self::MAX_DEPTH : $this->limits->maxAttributeDepth;

        foreach ($attributes as $key => $value) {
            $this->assertValidKey($key);
            $this->assertSupportedValue($value, 1, $maxDepth);
        }
    }

    /**
     * @throws InvalidAttributeException when the value is not a scalar, null, or
     *                                   a bounded list/map of scalars
     */
    private function assertSupportedValue(mixed $value, int $depth, int $maxDepth): void
    {
        if (is_scalar($value) || $value === null) {
            $maxValue = $this->limits === null ? self::MAX_VALUE_BYTES : $this->limits->maxAttributeValueBytes;
            if (is_string($value) && strlen($value) > $maxValue) {
                throw InvalidAttributeException::valueTooLong(strlen($value), $maxValue);
            }

            return;
        }

        if (is_array($value)) {
            if ($depth >= $maxDepth) {
                throw InvalidAttributeException::valueTooDeep($depth, $maxDepth);
            }

            foreach ($value as $member) {
                $this->assertSupportedValue($member, $depth + 1, $maxDepth);
            }

            return;
        }

        throw InvalidAttributeException::unsupportedValue();
    }
}
