<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Exception;

use InvalidArgumentException;

/**
 * Raised when a string value (name, kind, occurrence type, or snapshot type)
 * cannot be accepted.
 *
 * Messages describe the violated rule only. The rejected value is never
 * included: it is untrusted, may carry a secret, and may itself contain the
 * control characters the validation exists to keep out of logs and exports.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#executionKind
 */
final class InvalidStringValueException extends InvalidArgumentException implements InvalidValueExceptionInterface
{
    public static function emptyValue(): self
    {
        return new self('String value must not be empty.');
    }

    public static function tooLong(int $length, int $maximum): self
    {
        return new self(
            sprintf(
                'String value must not exceed %d bytes, got %d.',
                $maximum,
                $length,
            ),
        );
    }

    public static function unsupportedCharacters(): self
    {
        return new self(
            'String value must consist of letters, digits, and the separators '
            . '"." "-" "_" only; whitespace, control characters, and other '
            . 'symbols are rejected.',
        );
    }

    public static function malformedOccurrenceType(): self
    {
        return new self(
            'Occurrence type must be lowercase, dot-separated, and end with a '
            . 'version such as ".v1" (e.g. "itpack.configuration.loaded.v1").',
        );
    }

    public static function negativeValue(string $what): self
    {
        return new self(
            sprintf('Value for %s must not be negative.', $what),
        );
    }
}
