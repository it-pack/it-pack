<?php

declare(strict_types=1);

namespace ItPack\RunIt\Behavior\Domain;

use ItPack\RunIt\Contract\Domain\AttributeValidatorInterface;
use ItPack\RunIt\Contract\Exception\InvalidAttributeException;
use ItPack\RunIt\Contract\Shared\KeyPattern;
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
final class AttributeValidator implements AttributeValidatorInterface
{
    public function __construct(
        private readonly SecurityLimits $limits = new SecurityLimits(),
    ) {
    }

    public function assertValidKey(string $key): void
    {
        if ($key === '') {
            throw InvalidAttributeException::emptyKey();
        }

        $maxKey = $this->limits->maxAttributeKeyBytes;
        $length = strlen($key);

        if ($length > $maxKey) {
            throw InvalidAttributeException::keyTooLong($length, $maxKey);
        }

        if (preg_match(KeyPattern::VALIDATED_KEY, $key) !== 1) {
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
        $maxCount = $this->limits->maxAttributeCount;
        $count = count($attributes);

        if ($count > $maxCount) {
            throw InvalidAttributeException::tooManyAttributes($count, $maxCount);
        }

        foreach ($attributes as $key => $value) {
            $this->assertValidKey($key);
            $this->walker()->assertSupported($value);
        }
    }

    /**
     * The attribute rule set shares the bounded primitive walk with the context
     * rules; per-array entry counts are not checked here because the
     * collection-wide count bound is asserted above with its own violation.
     */
    private function walker(): BoundedPrimitiveValueWalker
    {
        return new BoundedPrimitiveValueWalker(
            $this->limits,
            static fn (int $length, int $maximum): InvalidAttributeException => InvalidAttributeException::valueTooLong($length, $maximum),
            static fn (int $depth, int $maximum): InvalidAttributeException => InvalidAttributeException::valueTooDeep($depth, $maximum),
            static fn (): InvalidAttributeException => InvalidAttributeException::unsupportedValue(),
        );
    }
}
