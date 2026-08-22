<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Domain;

use ItPack\CommandIt\Contract\Command\ArgumentInterface;
use ItPack\CommandIt\Contract\Command\CommandDefinitionInterface;
use ItPack\CommandIt\Contract\Command\OptionInterface;
use ItPack\CommandIt\Contract\ValueObject\CommandNameInterface;

/**
 * The immutable blueprint of a command.
 *
 * The {@see self::signature()} is computed on demand from the name, arguments,
 * and options. Because this class is `readonly`, the signature is exposed as a
 * computed method rather than a `get` property hook (PHP forbids hooked
 * properties on `readonly` classes).
 *
 * @api
 */
final readonly class CommandDefinition implements CommandDefinitionInterface
{
    /**
     * @param list<ArgumentInterface>  $arguments
     * @param list<OptionInterface>     $options
     */
    public function __construct(
        private CommandNameInterface $name,
        private string $description = '',
        private array $arguments = [],
        private array $options = [],
    ) {
    }

    public function name(): CommandNameInterface
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function arguments(): array
    {
        return $this->arguments;
    }

    public function options(): array
    {
        return $this->options;
    }

    public function signature(): string
    {
        $parts = [$this->name->toString()];

        foreach ($this->arguments as $argument) {
            $token = $argument->name()->toString();
            $parts[] = $argument->required() ? '<' . $token . '>' : '[' . $token . ']';
        }

        foreach ($this->options as $option) {
            $parts[] = '--' . $option->name()->toString();
        }

        return \implode(' ', $parts);
    }
}
