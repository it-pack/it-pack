<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Command;

use ItPack\CommandIt\Contract\ValueObject\CommandNameInterface;

/**
 * The immutable blueprint of a command: its name, description, arguments,
 * options, and the handler key used to resolve the handler at execution time.
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
     * The key used by the handler resolver to locate the handler implementation.
     */
    public function handlerKey(): string;

    /**
     * The computed CLI signature, e.g. `cache:clear <source> [--force]`.
     */
    public function signature(): string;
}
