<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Context;

use ItPack\RunIt\Contract\Domain\ContextValueValidatorInterface;
use ItPack\RunIt\Contract\Exception\InvalidContextValueException;
use ItPack\RunIt\Domain\Shared\SecurityLimits;

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
final class ContextValueValidator implements ContextValueValidatorInterface
{
    private const string KEY_PATTERN = '/^[A-Za-z0-9._-]++\z/';

    public function __construct(
        private readonly SecurityLimits $limits = new SecurityLimits(),
    ) {
    }

    public function assertValidKey(string $key): void
    {
        if ($key === '') {
            throw InvalidContextValueException::emptyKey();
        }

        $maxKey = $this->limits->maxAttributeKeyBytes;
        $length = strlen($key);

        if ($length > $maxKey) {
            throw InvalidContextValueException::keyTooLong($length, $maxKey);
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
        $maxDepth = $this->limits->maxAttributeDepth;

        $this->assertSupportedValueRecursive($value, 1, $maxDepth);
    }

    /**
     * Recursively rejects values that are not scalars, null, or bounded lists/maps
     * of scalars. Nesting depth is bounded so a deeply nested structure cannot
     * recurse until the stack overflows.
     *
     * @throws InvalidContextValueException when the value is not a scalar, null, or
     *                                   a bounded list/map of scalars, or exceeds the
     *                                   depth bound
     */
    private function assertSupportedValueRecursive(mixed $value, int $depth, int $maxDepth): void
    {
        if (is_scalar($value) || $value === null) {
            $maxValue = $this->limits->maxAttributeValueBytes;

            if (is_string($value) && strlen($value) > $maxValue) {
                throw InvalidContextValueException::valueTooLarge(strlen($value), $maxValue);
            }

            return;
        }

        if (is_array($value)) {
            if ($depth >= $maxDepth) {
                throw InvalidContextValueException::valueTooDeep($depth, $maxDepth);
            }

            if (count($value) > $this->limits->maxAttributeCount) {
                throw InvalidContextValueException::unsupportedValue();
            }

            foreach ($value as $member) {
                $this->assertSupportedValueRecursive($member, $depth + 1, $maxDepth);
            }

            return;
        }

        throw InvalidContextValueException::unsupportedValue();
    }
}
