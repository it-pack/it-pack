<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain;

use ItPack\AsyncIt\Contract\ValueObjectInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;

/**
 * Opaque token issued by a context storage for one attached execution scope.
 *
 * Replaces the per-runtime Swoole/Swow/Revolt token variants and the
 * inconsistent `MemoryContextToken` with a single `readonly` value object.
 * Equality is by id; the storage uses it to enforce last-in-first-out
 * detachment of the correct scope.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class ContextToken implements ContextTokenInterface, ValueObjectInterface
{
    public function __construct(public readonly int $id)
    {
    }

    public function id(): int
    {
        return $this->id;
    }
}
