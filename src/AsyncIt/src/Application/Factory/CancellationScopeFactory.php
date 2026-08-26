<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Domain\CancellationScope;

/**
 * Builds an observed, cooperative {@see CancellationScope}.
 *
 * @see https://w3id.org/itpack/asyncit#CancellationScope
 */
final class CancellationScopeFactory implements CancellationScopeFactoryInterface
{
    public function create(): CancellationScopeInterface
    {
        return new CancellationScope();
    }
}
