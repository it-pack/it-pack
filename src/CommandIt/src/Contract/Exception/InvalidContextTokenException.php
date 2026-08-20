<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Exception;

/**
 * Thrown when a context token does not match the current top of the stack.
 *
 * @api
 */
final class InvalidContextTokenException extends \LogicException implements InvalidContextTokenExceptionInterface
{
    public static function mismatched(): self
    {
        return new self('The context token does not match the current top of the stack.');
    }
}
