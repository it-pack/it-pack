<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use Generator;
use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\TaskStreamInterface;

/**
 * Builds {@see \ItPack\AsyncIt\Domain\TaskStream} instances.
 *
 * Construction of domain objects is restricted to Factory/Builder namespaces
 * per the {@see \ItPack\AsyncIt\Contract\EngineSchedulerInterface} architectural
 * rule.
 */
interface TaskStreamFactoryInterface
{
    /**
     * Creates a new {@see TaskStreamInterface} wrapping the scheduler and
     * generator.
     */
    public function create(EngineSchedulerInterface $scheduler, Generator $generator): TaskStreamInterface;
}
