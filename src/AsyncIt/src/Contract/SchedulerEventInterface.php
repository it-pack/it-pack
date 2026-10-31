<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

use ItPack\AsyncIt\Contract\Port\AwaitPoint;
use ItPack\AsyncIt\Contract\Port\SchedulerEventType;
use ItPack\AsyncIt\Contract\Port\TaskStatus;

interface SchedulerEventInterface
{
    public TaskInterface $task {
        get;
    }

    public SchedulerEventType $type {
        get;
    }

    public TaskStatus $status {
        get;
    }

    public ?AwaitPoint $awaitPoint {
        get;
    }

    public ?TaskIdInterface $parentId {
        get;
    }
}
