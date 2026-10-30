<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract\Port;

/**
 * Policy for ordering tasks in the scheduler's runnable queue.
 *
 * @see https://w3id.org/itpack/asyncit#SchedulingPolicy
 * @see https://w3id.org/itpack/asyncit-taxonomy#scheduling-policy
 */
enum SchedulingPolicy: string
{
    case Fifo = 'fifo';

    case Priority = 'priority';

    case WeightedFair = 'weighted-fair';
}
