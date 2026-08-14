<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Exception;

use InvalidArgumentException;

/**
 * Raised when an attribute key or value cannot be accepted.
 *
 * Messages describe the violated rule only. A rejected value is never echoed
 * into the message when it may carry a secret or control characters.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Observation
 */
final class InvalidAttributeException extends InvalidArgumentException implements InvalidValueExceptionInterface
{
    public static function emptyKey(): self
    {
        return new self('Attribute key must not be empty.');
    }

    public static function keyTooLong(int $length, int $maximum): self
    {
        return new self(
            sprintf('Attribute key must not exceed %d bytes, got %d.', $maximum, $length),
        );
    }

    public static function unsupportedKeyCharacters(): self
    {
        return new self(
            'Attribute key must consist of letters, digits, and the separators '
            . '"." "-" "_" only.',
        );
    }

    public static function tooManyAttributes(int $count, int $maximum): self
    {
        return new self(
            sprintf('Attribute count must not exceed %d, got %d.', $maximum, $count),
        );
    }

    public static function unsupportedValue(): self
    {
        return new self(
            'Attribute value must be a scalar, null, or a list/map of scalars; '
            . 'objects and resources are rejected.',
        );
    }
}
