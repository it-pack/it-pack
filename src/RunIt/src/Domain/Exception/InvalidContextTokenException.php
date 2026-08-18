<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Exception;

use ItPack\RunIt\Contract\Exception\RunItExceptionInterface;
use LogicException;

/**
 * Raised when a context token is invalid for the receiving storage.
 *
 * A token is only valid for the storage that issued it. Supplying a token from another
 * storage, a forged token, or a token that was already detached is rejected so the
 * wrong scope is never restored.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class InvalidContextTokenException extends LogicException implements RunItExceptionInterface
{
    public static function mismatched(): self
    {
        return new self('Context token does not belong to this storage.');
    }

    public static function alreadyDetached(): self
    {
        return new self('Context token has already been detached.');
    }
}
