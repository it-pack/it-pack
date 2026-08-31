<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Adapter\Swoole;

use ItPack\AsyncIt\Contract\ValueObjectInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;

/**
 * Token issued by {@see SwooleCoroutineContextStorage} for one attached scope.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class SwooleContextToken implements ContextTokenInterface, ValueObjectInterface
{
    public function __construct(public int $id)
    {
    }

    public function id(): int
    {
        return $this->id;
    }
}
