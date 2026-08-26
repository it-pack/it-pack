<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Context;

use ItPack\RunIt\Contract\Port\ContextTokenInterface;

/**
 * Opaque token issued by {@see MemoryContextStorage} for one attached scope.
 *
 * Equality is by identity of the issued integer id; the storage uses it to
 * enforce last-in-first-out detachment of the correct scope.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class MemoryContextToken implements ContextTokenInterface
{
    public function __construct(public readonly int $id)
    {
    }
}
