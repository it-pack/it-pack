<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use Fiber;

/**
 * Concrete {@see FiberFactoryInterface} building a PHP {@see Fiber}.
 *
 * @see https://w3id.org/itpack/asyncit#Scheduler
 */
final class FiberFactory implements FiberFactoryInterface
{
    public function create(callable $operation): Fiber
    {
        return new Fiber($operation);
    }
}
