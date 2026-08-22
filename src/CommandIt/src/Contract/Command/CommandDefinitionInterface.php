<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Command;

use ItPack\CommandIt\Contract\ValueObject\CommandNameInterface;

/**
 * The immutable blueprint of a command: its name, description, arguments,
 * and options.
 *
 * The {@see self::signature()} is computed (not stored) from the name, arguments,
 * and options, and is the canonical CLI projection of the definition.
 *
 * @api
 */
interface CommandDefinitionInterface
{
    public function name(): CommandNameInterface;

    public function description(): string;

    /**
     * @return list<ArgumentInterface>
     */
    public function arguments(): array;

    /**
     * @return list<OptionInterface>
     */
    public function options(): array;

    /**
     * The computed CLI signature, e.g. `cache:clear <source> [--force]`.
     */
    public function signature(): string;
}
