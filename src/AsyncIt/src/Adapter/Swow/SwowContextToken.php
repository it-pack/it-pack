<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Adapter\Swow;

use ItPack\RunIt\Contract\Port\ContextTokenInterface;

/**
 * Token issued by {@see SwowCoroutineContextStorage} for one attached scope.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class SwowContextToken implements ContextTokenInterface
{
    public function __construct(public int $id)
    {
    }
}
