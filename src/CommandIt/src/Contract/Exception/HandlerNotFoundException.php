<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Exception;

/**
 * Thrown when no handler is registered for a command name.
 *
 * This is a configuration/registration error, not a business failure, and it is
 * raised before any execution begins so the dispatch is rejected early.
 *
 * @api
 */
final class HandlerNotFoundException extends \RuntimeException implements CommandExceptionInterface, HandlerNotFoundExceptionInterface
{
    public static function forCommand(string $name): self
    {
        return new self(\sprintf('No handler is registered for command "%s".', $name));
    }
}
