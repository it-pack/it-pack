<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\AsyncIt\Contract\CancellationScopeInterface;

/**
 * Creates an observed {@see CancellationScopeInterface}.
 *
 * @see https://w3id.org/itpack/asyncit#CancellationScope
 */
interface CancellationScopeFactoryInterface
{
    public function create(): CancellationScopeInterface;
}
