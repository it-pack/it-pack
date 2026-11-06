<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain;

use ItPack\AsyncIt\Contract\StringableValueObjectInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;

/**
 * Opaque token issued by a context storage for one attached execution scope.
 *
 * Replaces the per-runtime Swoole/Swow/Revolt token variants and the
 * inconsistent `MemoryContextToken` with a single `readonly` value object.
 * The id is stable but is not proof of ownership; storages require the
 * original token object for last-in-first-out detachment.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class ContextToken implements ContextTokenInterface, StringableValueObjectInterface
{
    public function __construct(public int $id)
    {
    }

    public function __toString(): string
    {
        return (string) $this->id;
    }

    public function id(): int
    {
        return $this->id;
    }
}
