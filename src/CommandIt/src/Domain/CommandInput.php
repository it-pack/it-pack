<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Domain;

use ItPack\CommandIt\Contract\Command\CommandInputInterface;
use ItPack\CommandIt\Contract\Exception\InvalidCommandException;

/**
 * Immutable resolved input for a command.
 *
 * Values are resolved and type-coerced before construction (the input parser in
 * a later milestone owns coercion), so this object stores them verbatim and is
 * never mutated. A `readonly` class cannot carry a `set` hook, so per-value
 * coercion is performed by the producer, not by this value object.
 *
 * @api
 */
final readonly class CommandInput implements CommandInputInterface
{
    /**
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $options
     */
    public function __construct(
        private array $arguments = [],
        private array $options = [],
    ) {
    }

    public function arguments(): array
    {
        return $this->arguments;
    }

    public function options(): array
    {
        return $this->options;
    }

    public function hasArgument(string $name): bool
    {
        return \array_key_exists($name, $this->arguments);
    }

    public function hasOption(string $name): bool
    {
        return \array_key_exists($name, $this->options);
    }

    public function argument(string $name): mixed
    {
        if (!\array_key_exists($name, $this->arguments)) {
            throw InvalidCommandException::malformed(\sprintf('Missing argument "%s".', $name));
        }

        return $this->arguments[$name];
    }

    public function option(string $name): mixed
    {
        if (!\array_key_exists($name, $this->options)) {
            throw InvalidCommandException::malformed(\sprintf('Missing option "%s".', $name));
        }

        return $this->options[$name];
    }
}
