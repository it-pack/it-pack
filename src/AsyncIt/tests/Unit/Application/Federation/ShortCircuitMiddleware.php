<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Federation;

use ItPack\AsyncIt\Contract\SchedulerMiddlewareInterface;
use ItPack\AsyncIt\Contract\TaskInterface;

final class ShortCircuitMiddleware implements SchedulerMiddlewareInterface
{
    public function process(TaskInterface $task, callable $next): mixed
    {
        return 'short-circuited';
    }
}
