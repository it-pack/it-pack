<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Exception;

/**
 * Thrown when user-supplied command input (a name, option, argument, or payload)
 * violates a domain validation rule.
 *
 * @api
 */
final class InvalidCommandException extends \InvalidArgumentException implements CommandExceptionInterface, InvalidCommandExceptionInterface
{
    public static function emptyValue(): self
    {
        return new self('A command value must not be empty.');
    }

    public static function valueTooLong(int $max): self
    {
        return new self(\sprintf('A command value must not exceed %d characters.', $max));
    }

    public static function controlCharacters(): self
    {
        return new self('A command value must not contain control characters.');
    }

    public static function malformed(string $reason): self
    {
        return new self(\sprintf('Malformed command value: %s', $reason));
    }

    public static function duplicateCommand(string $name): self
    {
        return new self(\sprintf('A command named "%s" is already registered.', $name));
    }
}
