<?php

declare(strict_types=1);

namespace ItPack\CommandIt\SDK;

use ItPack\CommandIt\Contract\Command\CommandDefinitionInterface;
use ItPack\CommandIt\Contract\Command\CommandInputInterface;
use ItPack\CommandIt\Contract\Command\CommandInterface;
use ItPack\CommandIt\Contract\Exception\InvalidCommandException;
use ItPack\CommandIt\Domain\Command;
use ItPack\CommandIt\Domain\CommandInput;

/**
 * Fluent builder for a {@see CommandInterface} from a definition and its input.
 *
 * The command name is taken from the definition, so a command and its definition
 * always agree. The receiver is mutated per call (a builder), but {@see self::build()}
 * returns an immutable {@see Command}.
 *
 * @api
 */
final class CommandBuilder
{
    private ?CommandDefinitionInterface $definition = null;

    private CommandInputInterface $input;

    private mixed $payload = null;

    public function __construct()
    {
        $this->input = new CommandInput();
    }

    public function definition(CommandDefinitionInterface $definition): self
    {
        $this->definition = $definition;

        return $this;
    }

    public function input(CommandInputInterface $input): self
    {
        $this->input = $input;

        return $this;
    }

    public function payload(mixed $payload): self
    {
        $this->payload = $payload;

        return $this;
    }

    public function build(): CommandInterface
    {
        if ($this->definition === null) {
            throw InvalidCommandException::malformed('A command requires a definition.');
        }

        return new Command($this->definition->name(), $this->input, $this->payload);
    }
}
