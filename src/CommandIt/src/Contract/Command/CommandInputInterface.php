<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Command;

use ItPack\CommandIt\Contract\Exception\InvalidCommandException;

/**
 * Immutable resolved input for a command: argument values and option values.
 *
 * Values are resolved and (where applicable) type-coerced before construction,
 * so this object never mutates and performs no coercion itself.
 *
 * @api
 */
interface CommandInputInterface
{
    /**
     * @return array<string, mixed>
     */
    public function arguments(): array;

    /**
     * @return array<string, mixed>
     */
    public function options(): array;

    public function hasArgument(string $name): bool;

    public function hasOption(string $name): bool;

    /**
     * @return mixed
     *
     * @throws InvalidCommandException when the argument is absent
     */
    public function argument(string $name): mixed;

    /**
     * @return mixed
     *
     * @throws InvalidCommandException when the option is absent
     */
    public function option(string $name): mixed;
}
