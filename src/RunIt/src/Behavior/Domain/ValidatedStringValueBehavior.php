<?php

declare(strict_types=1);

namespace ItPack\RunIt\Behavior\Domain;

use ItPack\RunIt\Contract\Domain\StringValueInterface;
use ItPack\RunIt\Contract\Domain\StringValueValidatorInterface;
use ItPack\RunIt\Contract\Exception\InvalidStringValueException;
use ItPack\RunIt\Contract\Exception\InvalidValueExceptionInterface;

/**
 * Construction behavior shared by every RunIt string value (name or kind).
 *
 * The value is validated once, on construction, and stored verbatim. Nothing
 * trims, lowercases, or transcodes it, so `fromString()` round-trips exactly.
 *
 * The constructor is private: a value is only ever created through the
 * `fromString()` named constructor. Subclasses may pass a stricter validator
 * (for example, the occurrence-type format) by overriding the default.
 *
 * @see https://w3id.org/itpack/runit#executionKind
 */
trait ValidatedStringValueBehavior
{
    use StringProviderBehavior;

    /**
     * The canonical string projection of this value.
     *
     * Declared `readonly` (rather than a virtual hook) so the consuming class
     * can stay a `readonly class`; the value is validated and assigned exactly
     * once, on construction, and never mutated. Callers read `$value->value`
     * or `toString()`.
     */
    public readonly string $value;

    /**
     * @throws InvalidValueExceptionInterface
     */
    private function __construct(
        string $value,
        StringValueValidatorInterface $validator = new \ItPack\RunIt\Behavior\Domain\StringValueValidator(),
    ) {
        $validator->validate($value);

        $this->value = $value;
    }

    /**
     * Creates a value from an already known string.
     *
     * @throws InvalidStringValueException when the value is empty, too long, or
     *                                    contains unsupported characters
     */
    public static function fromString(string $value): static
    {
        return new static($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(StringValueInterface $other): bool
    {
        return $other::class === static::class
            && $other->toString() === $this->toString();
    }
}
