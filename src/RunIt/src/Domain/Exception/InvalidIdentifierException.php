<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Exception;

use InvalidArgumentException;
use ItPack\RunIt\Contract\Exception\InvalidValueExceptionInterface;

/**
 * Raised when a value cannot be accepted as a RunIt identifier.
 *
 * Messages describe the violated rule only. The rejected value is never
 * included: it is untrusted, may carry a secret, and may itself contain the
 * control characters the validation exists to keep out of logs.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#executionId
 */
final class InvalidIdentifierException extends InvalidArgumentException implements InvalidValueExceptionInterface
{
    public static function emptyValue(): self
    {
        return new self('Identifier must not be empty.');
    }

    public static function tooLong(int $length, int $maximum): self
    {
        return new self(
            sprintf(
                'Identifier must not exceed %d bytes, got %d.',
                $maximum,
                $length,
            ),
        );
    }

    public static function unsupportedCharacters(): self
    {
        return new self(
            'Identifier must consist of visible ASCII characters only; '
            . 'whitespace, control characters, and non-ASCII bytes are rejected.',
        );
    }
}
