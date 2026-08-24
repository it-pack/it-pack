<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Context;

use ItPack\RunIt\Behavior\Domain\BoundedKeyGuard;
use ItPack\RunIt\Behavior\Domain\BoundedPrimitiveValueWalker;
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
final readonly class ContextValueValidator implements ContextValueValidatorInterface
{
    public function __construct(
        private readonly SecurityLimits $limits = new SecurityLimits(),
    ) {
    }

    public function assertValidKey(string $key): void
    {
        $this->keyGuard()->assertValidKey($key);
    }

    /**
     * The context key rule set shares the bounded key guard with the attribute
     * rules; only the exception family differs.
     */
    private function keyGuard(): BoundedKeyGuard
    {
        return new BoundedKeyGuard(
            $this->limits,
            static fn (): InvalidContextValueException => InvalidContextValueException::emptyKey(),
            static fn (int $length, int $maximum): InvalidContextValueException => InvalidContextValueException::keyTooLong($length, $maximum),
            static fn (): InvalidContextValueException => InvalidContextValueException::unsupportedKeyCharacters(),
        );
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
        $this->walker()->assertSupported($value);
    }

    /**
     * The context rule set shares the bounded primitive walk with the attribute
     * rules and additionally rejects arrays that exceed the per-array entry
     * bound at any nesting level.
     */
    private function walker(): BoundedPrimitiveValueWalker
    {
        return new BoundedPrimitiveValueWalker(
            $this->limits,
            static fn (int $length, int $maximum): InvalidContextValueException => InvalidContextValueException::valueTooLarge($length, $maximum),
            static fn (int $depth, int $maximum): InvalidContextValueException => InvalidContextValueException::valueTooDeep($depth, $maximum),
            static fn (): InvalidContextValueException => InvalidContextValueException::unsupportedValue(),
            static fn (int $count, int $maximum): InvalidContextValueException => InvalidContextValueException::tooManyEntries($count, $maximum),
            rejectOversizedArrays: true,
        );
    }
}
