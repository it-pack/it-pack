<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Adapter\Input;

use ItPack\CommandIt\Behavior\Domain\InputCoercionBehavior;
use ItPack\CommandIt\Contract\Command\CommandDefinitionInterface;
use ItPack\CommandIt\Contract\Command\CommandInputInterface;
use ItPack\CommandIt\Domain\CommandInput;

/**
 * Adapts a single flat associative array into an immutable {@see CommandInputInterface}.
 *
 * Values are routed to arguments or options by consulting the definition's
 * declared names, then validated and coerced exactly like the core
 * {@see \ItPack\CommandIt\Application\CommandInputParser} (shared behavior). An
 * unknown key is rejected so malformed input never reaches a handler.
 *
 * @api
 */
final class ArrayInputAdapter
{
    use InputCoercionBehavior;

    /**
     * @param array<string, mixed> $values
     */
    public function parse(CommandDefinitionInterface $definition, array $values): CommandInputInterface
    {
        $argumentNames = $this->names($definition->arguments());
        $optionNames = $this->names($definition->options());

        $arguments = [];
        $options = [];

        foreach ($values as $key => $value) {
            if (\in_array($key, $argumentNames, true)) {
                $arguments[$key] = $value;
            } elseif (\in_array($key, $optionNames, true)) {
                $options[$key] = $value;
            } else {
                throw new \ItPack\CommandIt\Contract\Exception\InvalidCommandException(
                    \sprintf('Unknown input key "%s".', $key),
                );
            }
        }

        return new CommandInput(
            $this->resolveInputs($definition->arguments(), $arguments, 'argument'),
            $this->resolveInputs($definition->options(), $options, 'option'),
        );
    }
}
