<?php

declare(strict_types=1);

namespace ItPack\CommandIt\SDK;

use ItPack\CommandIt\Contract\Command\ArgumentInterface;
use ItPack\CommandIt\Contract\Command\CommandDefinitionInterface;
use ItPack\CommandIt\Contract\Command\OptionInterface;
use ItPack\CommandIt\Contract\Exception\InvalidCommandException;
use ItPack\CommandIt\Contract\ValueObject\CommandNameInterface;
use ItPack\CommandIt\Domain\CommandDefinition;
use ItPack\CommandIt\Domain\ValueObject\CommandName;

/**
 * Immutable, fluent builder for a {@see CommandDefinitionInterface}.
 *
 * Every modifier returns a new builder instance; the receiver is never mutated,
 * and {@see self::build()} returns a validated, `readonly` definition whose
 * {@see CommandDefinitionInterface::signature()} is computed on demand.
 *
 * @api
 */
final class CommandDefinitionBuilder
{
    private ?CommandNameInterface $name = null;

    private string $description = '';

    /** @var list<ArgumentInterface> */
    private array $arguments = [];

    /** @var list<OptionInterface> */
    private array $options = [];

    private string $handlerKey = '';

    public function name(CommandNameInterface|string $name): self
    {
        $clone = clone $this;
        $clone->name = \is_string($name) ? CommandName::fromString($name) : $name;

        return $clone;
    }

    public function description(string $description): self
    {
        $clone = clone $this;
        $clone->description = $description;

        return $clone;
    }

    public function argument(ArgumentInterface $argument): self
    {
        $clone = clone $this;
        $clone->arguments = [...$this->arguments, $argument];

        return $clone;
    }

    public function option(OptionInterface $option): self
    {
        $clone = clone $this;
        $clone->options = [...$this->options, $option];

        return $clone;
    }

    /**
     * Sets the key the handler resolver uses to locate the handler implementation.
     */
    public function handler(string $handlerKey): self
    {
        $clone = clone $this;
        $clone->handlerKey = $handlerKey;

        return $clone;
    }

    public function build(): CommandDefinitionInterface
    {
        if ($this->name === null) {
            throw InvalidCommandException::malformed('A command definition requires a name.');
        }

        return new CommandDefinition(
            $this->name,
            $this->description,
            $this->arguments,
            $this->options,
            $this->handlerKey,
        );
    }
}
