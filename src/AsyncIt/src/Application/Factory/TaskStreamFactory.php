<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use Generator;
use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\TaskStreamInterface;
use ItPack\AsyncIt\Domain\TaskStream;

final class TaskStreamFactory implements TaskStreamFactoryInterface
{
    public function create(EngineSchedulerInterface $scheduler, Generator $generator): TaskStreamInterface
    {
        return new TaskStream($scheduler, $generator);
    }
}
