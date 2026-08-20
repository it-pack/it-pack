<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Behavior\Domain;

use ItPack\CommandIt\Contract\Exception\InvalidCommandException;
use ItPack\CommandIt\Contract\ValueObject\ValidatedStringInterface;

/**
 * Construction and comparison behavior shared by every CommandIt string value.
 *
 * The value is validated once, on construction, and stored verbatim. Nothing
 * trims, lowercases, or transcodes it, so {@see self::fromString()} round-trips
 * exactly. The constructor is private: a value is only created through the
 * named constructor {@see self::fromString()}.
 *
 * Subclasses tighten the rules by overriding {@see self::assertAdditionalRules()};
 * the base safety rules (non-empty, bounded, no control characters) always run.
 */
trait ValidatedStringValueBehavior
{
    private function __construct(
        private readonly string $value,
    ) {
        static::validate($this->value);
    }

    /**
     * @throws InvalidCommandException when the value is empty, too long, or
     *                                contains control characters
     */
    public static function fromString(string $value): static
    {
        return new static($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(ValidatedStringInterface $other): bool
    {
        return $other::class === static::class
            && $other->toString() === $this->toString();
    }

    /**
     * @throws InvalidCommandException
     */
    protected static function validate(string $value): void
    {
        if (trim($value) === '') {
            throw InvalidCommandException::emptyValue();
        }

        if (mb_strlen($value, 'UTF-8') > 255) {
            throw InvalidCommandException::valueTooLong(255);
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw InvalidCommandException::controlCharacters();
        }

        static::assertAdditionalRules($value);
    }

    /**
     * Hook for subclasses to add format rules without re-implementing the base.
     */
    protected static function assertAdditionalRules(string $value): void
    {
    }
}
