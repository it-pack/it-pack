<?php

declare(strict_types=1);

namespace ItPack\RunIt\Behavior\Domain;

use ItPack\RunIt\Domain\Exception\InvalidIdentifierException;
use ItPack\RunIt\Domain\Shared\StringIdentifierValidator;

/**
 * Construction behavior shared by every RunIt identifier value object.
 *
 * The value is validated once, on construction, and stored verbatim. Nothing
 * trims, lowercases, or transcodes it, so `fromString()` round-trips exactly
 * and two distinct inputs can never collapse into one identity.
 *
 * The constructor is private: an identifier is only ever created through the
 * `fromString()` named constructor or through a
 * {@link \ItPack\RunIt\Contract\Domain\Factory\StringIdentifierFactoryInterface}.
 *
 * @see https://w3id.org/itpack/runit#executionId
 */
trait ValidatedStringIdentifierBehavior
{
    use StringIdentifierBehavior;

    /**
     * The canonical string projection of this identifier.
     *
     * Declared `readonly` (rather than a virtual hook) so the consuming class
     * can stay a `readonly class`; the value is assigned exactly once, on
     * construction, and never mutated. Callers read `$id->value` or
     * `toString()`.
     */
    public readonly string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Creates an identifier from an already known value.
     *
     * @throws InvalidIdentifierException when the value is empty, too long, or
     *                                    contains whitespace, control, or
     *                                    non-ASCII characters
     */
    public static function fromString(string $value): static
    {
        StringIdentifierValidator::assertValid($value);

        return new static($value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
