<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Adapter\Revolt;

use ItPack\AsyncIt\Contract\ValueObjectInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;

/**
 * Token issued by {@see RevoltFiberContextStorage} for one attached scope.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class RevoltContextToken implements ContextTokenInterface, ValueObjectInterface
{
    public function __construct(public int $id)
    {
    }

    public function id(): int
    {
        return $this->id;
    }
}
