<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Adapter\CLI;

use ItPack\CommandIt\Behavior\Domain\InputCoercionBehavior;
use ItPack\CommandIt\Contract\Command\ArgumentInterface;
use ItPack\CommandIt\Contract\Command\CommandDefinitionInterface;
use ItPack\CommandIt\Contract\Command\CommandInputInterface;
use ItPack\CommandIt\Contract\Command\OptionInterface;
use ItPack\CommandIt\Domain\CommandInput;

/**
 * Parses raw `$argv` tokens (after the script name and command name) into an
 * immutable {@see CommandInputInterface}.
 *
 * Positional tokens fill arguments in declaration order; `--name` / `--name=value`
 * / `--name value` tokens fill options. Values are then validated and coerced by
 * the shared {@see InputCoercionBehavior}, so this adapter agrees exactly with
 * the core {@see \ItPack\CommandIt\Application\CommandInputParser}.
 *
 * @api
 */
final class ArgvInputParser
{
    use InputCoercionBehavior;

    /**
     * @param list<string> $tokens argv tokens after script + command name
     */
    public function parse(CommandDefinitionInterface $definition, array $tokens): CommandInputInterface
    {
        [$arguments, $options] = $this->split($tokens, $definition);

        return new CommandInput(
            $this->resolveInputs($definition->arguments(), $arguments, 'argument'),
            $this->resolveInputs($definition->options(), $options, 'option'),
        );
    }

    /**
     * @param list<string> $tokens
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function split(array $tokens, CommandDefinitionInterface $definition): array
    {
        $arguments = [];
        $options = [];
        $argumentNames = $this->names($definition->arguments());
        $positionalIndex = 0;
        $count = \count($tokens);
        $i = 0;

        while ($i < $count) {
            $token = $tokens[$i];

            if (\str_starts_with($token, '--')) {
                $body = \substr($token, 2);

                if (\str_contains($body, '=')) {
                    [$name, $value] = \explode('=', $body, 2);
                    $options[$name] = $value;
                } else {
                    $next = $tokens[$i + 1] ?? null;
                    if ($next !== null && !\str_starts_with($next, '-')) {
                        $options[$body] = $next;
                        ++$i;
                    } else {
                        $options[$body] = true;
                    }
                }
            } else {
                if ($positionalIndex < \count($argumentNames)) {
                    $arguments[$argumentNames[$positionalIndex]] = $token;
                }
                ++$positionalIndex;
            }

            ++$i;
        }

        return [$arguments, $options];
    }

    /**
     * @param list<ArgumentInterface|OptionInterface> $specs
     *
     * @return list<string>
     */
    private function names(array $specs): array
    {
        return \array_map(static fn (ArgumentInterface|OptionInterface $spec): string => $spec->name()->toString(), $specs);
    }
}
