<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Adapter\CLI;

use ItPack\CommandIt\Behavior\Domain\InputCoercionBehavior;
use ItPack\CommandIt\Contract\Command\ArgumentInterface;
use ItPack\CommandIt\Contract\Command\CommandDefinitionInterface;
use ItPack\CommandIt\Contract\Command\CommandInputInterface;
use ItPack\CommandIt\Contract\Command\OptionInterface;
use ItPack\CommandIt\Contract\Exception\InvalidCommandException;
use ItPack\CommandIt\Domain\CommandInput;

/**
 * Parses raw `$argv` tokens (after the script name and command name) into an
 * immutable {@see CommandInputInterface}.
 *
 * Token grammar:
 *
 *  - Long options only: `--name`, `--name=value`, `--name value`.
 *  - A following token that looks like a negative number (`-5`, `-0.25`) is
 *    accepted as an option value; any other `-`-prefixed token is not.
 *  - `--` terminates option parsing; every later token is positional.
 *  - Negative numbers are accepted as positional arguments.
 *  - Short flags (`-f`) are rejected loudly rather than mis-parsed.
 *  - Unknown options and surplus positionals are rejected.
 *
 * Positional tokens fill arguments in declaration order; values are validated
 * and coerced by the shared {@see InputCoercionBehavior}, so this adapter
 * agrees exactly with the core {@see \ItPack\CommandIt\Application\CommandInputParser}.
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
        $providedOptions = [];
        $argumentNames = $this->names($definition->arguments());
        $declaredOptions = \array_flip($this->names($definition->options()));
        $positionalIndex = 0;
        $optionsEnded = false;
        $count = \count($tokens);
        $i = 0;

        while ($i < $count) {
            $token = $tokens[$i];

            if (!$optionsEnded && $token === '--') {
                $optionsEnded = true;
                ++$i;
                continue;
            }

            if (!$optionsEnded && \str_starts_with($token, '--') && \strlen($token) > 2) {
                $body = \substr($token, 2);

                if (\str_contains($body, '=')) {
                    [$name, $value] = \explode('=', $body, 2);
                    $providedOptions[] = $name;
                    $options[$name] = $value;
                    ++$i;
                    continue;
                }

                $next = $tokens[$i + 1] ?? null;
                if ($next !== null && (!\str_starts_with($next, '-') || \preg_match('/^-\d/', $next) === 1)) {
                    $providedOptions[] = $body;
                    $options[$body] = $next;
                    $i += 2;
                    continue;
                }

                $providedOptions[] = $body;
                $options[$body] = true;
                ++$i;
                continue;
            }

            if (!$optionsEnded && \str_starts_with($token, '-') && \preg_match('/^-\d/', $token) !== 1) {
                throw InvalidCommandException::malformed(\sprintf('short options are not supported: "%s"', $token));
            }

            if ($positionalIndex >= \count($argumentNames)) {
                throw InvalidCommandException::malformed(\sprintf('unexpected argument "%s"', $token));
            }

            $arguments[$argumentNames[$positionalIndex]] = $token;
            ++$positionalIndex;
            ++$i;
        }

        foreach ($providedOptions as $name) {
            if (!isset($declaredOptions[$name])) {
                throw InvalidCommandException::malformed(\sprintf('unknown option "--%s"', $name));
            }
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
