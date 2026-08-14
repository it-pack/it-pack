<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Exception;

use InvalidArgumentException;

/**
 * Raised when a context key, value, or entry cannot be accepted.
 *
 * Messages describe the violated rule only. A rejected value is never echoed
 * into the message when it may carry a secret or control characters.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#ExecutionContext
 */
final class InvalidContextValueException extends InvalidArgumentException implements InvalidValueExceptionInterface
{
    public static function emptyKey(): self
    {
        return new self('Context key must not be empty.');
    }

    public static function keyTooLong(int $length, int $maximum): self
    {
        return new self(
            sprintf('Context key must not exceed %d bytes, got %d.', $maximum, $length),
        );
    }

    public static function unsupportedKeyCharacters(): self
    {
        return new self(
            'Context key must consist of letters, digits, and the separators '
            . '"." "-" "_" only.',
        );
    }

    public static function unsupportedValue(): self
    {
        return new self(
            'Context value must be a scalar, null, or a list/map of scalars; '
            . 'objects and resources are rejected.',
        );
    }

    public static function valueTooLarge(int $length, int $maximum): self
    {
        return new self(
            sprintf('Context value must not exceed %d bytes, got %d.', $maximum, $length),
        );
    }

    public static function tooManyEntries(int $count, int $maximum): self
    {
        return new self(
            sprintf('Context must not exceed %d entries, got %d.', $maximum, $count),
        );
    }

    public static function collision(string $key): self
    {
        return new self(
            sprintf('Context key %s already exists and the collision policy is Throw.', $key),
        );
    }

    public static function missingKey(string $key): self
    {
        return new self(
            sprintf('Context key %s is not present.', $key),
        );
    }
}
