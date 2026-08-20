<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Application;

use ItPack\CommandIt\Behavior\Domain\InputCoercionBehavior;
use ItPack\CommandIt\Contract\Command\CommandDefinitionInterface;
use ItPack\CommandIt\Contract\Command\CommandInputInterface;
use ItPack\CommandIt\Domain\CommandInput;

/**
 * Core parser that validates and coerces separated argument/option arrays into
 * an immutable {@see CommandInputInterface}.
 *
 * @api
 */
final class CommandInputParser
{
    use InputCoercionBehavior;

    /**
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $options
     */
    public function parse(CommandDefinitionInterface $definition, array $arguments, array $options): CommandInputInterface
    {
        return new CommandInput(
            $this->resolveInputs($definition->arguments(), $arguments, 'argument'),
            $this->resolveInputs($definition->options(), $options, 'option'),
        );
    }
}
