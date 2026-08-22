<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Application;

use ItPack\CommandIt\Contract\Command\CommandDefinitionInterface;
use ItPack\CommandIt\Contract\Command\CommandInputInterface;

/**
 * Core parser that validates and coerces separated argument/option arrays into
 * an immutable {@see CommandInputInterface}.
 *
 * @api
 */
interface CommandInputParserInterface
{
    /**
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $options
     */
    public function parse(
        CommandDefinitionInterface $definition,
        array $arguments,
        array $options,
    ): CommandInputInterface;
}
