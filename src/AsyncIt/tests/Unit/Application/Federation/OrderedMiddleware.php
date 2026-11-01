<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Federation;

use ItPack\AsyncIt\Contract\SchedulerMiddlewareInterface;
use ItPack\AsyncIt\Contract\TaskInterface;

final readonly class OrderedMiddleware implements SchedulerMiddlewareInterface
{
    public function __construct(
        private OrderLog $log,
        private string $name,
    ) {
    }

    public function process(TaskInterface $task, callable $next): mixed
    {
        $this->log->entries[] = $this->name . '-before';
        $result = $next();
        $this->log->entries[] = $this->name . '-after';

        return $result;
    }
}
