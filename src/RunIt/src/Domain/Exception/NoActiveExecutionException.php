<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Exception;

use ItPack\RunIt\Contract\Exception\RunItExceptionInterface;
use RuntimeException;

/**
 * Raised when an ambient execution is required but none is active.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class NoActiveExecutionException extends RuntimeException implements RunItExceptionInterface
{
    public static function instance(): self
    {
        return new self('No execution is currently active.');
    }
}
