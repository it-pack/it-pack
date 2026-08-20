<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Behavior\Domain;

use ItPack\CommandIt\Domain\ValueObject\CommandName;

/**
 * Projects the command a domain artifact belongs to, exposing a stable
 * {@see self::isNamed()} comparison.
 *
 * @see https://w3id.org/itpack/runit#commandName
 */
trait CommandNamedBehavior
{
    abstract public function commandName(): CommandName;

    // @phpstan-ignore-next-line shipmonk.deadMethod Foundation trait projection; consumed by Milestone 2 artifacts.
    public function isNamed(CommandName $name): bool
    {
        return $this->commandName()->equals($name);
    }
}
