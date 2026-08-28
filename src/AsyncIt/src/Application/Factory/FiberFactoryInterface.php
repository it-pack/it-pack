<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use Fiber;

/**
 * Creates the Fiber that suspends/resumes a scheduled operation.
 *
 * Lives in the `Factory` namespace so the scheduler can obtain Fibers via DI
 * without calling `new` itself (per `ForbidNewOutsideFactoryBuilderRule`).
 *
 * @see https://w3id.org/itpack/asyncit#Scheduler
 */
interface FiberFactoryInterface
{
    /**
     * @return \Fiber<mixed, mixed, mixed, mixed>
     */
    public function create(callable $operation): Fiber;
}
