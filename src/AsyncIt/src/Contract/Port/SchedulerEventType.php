<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract\Port;

/**
 * Closed set of scheduler lifecycle event types.
 *
 * @see https://w3id.org/itpack/asyncit#Task
 * @see https://w3id.org/itpack/asyncit-taxonomy#occ-task-spawned
 * @see https://w3id.org/itpack/asyncit-taxonomy#occ-task-resumed
 * @see https://w3id.org/itpack/asyncit-taxonomy#occ-task-suspended
 * @see https://w3id.org/itpack/asyncit-taxonomy#occ-task-completed
 */
enum SchedulerEventType: string
{
    case Spawned = 'task.spawned';

    case Suspended = 'task.suspended';

    case Resumed = 'task.resumed';

    case Completed = 'task.completed';
}
