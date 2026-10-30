<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

use ItPack\AsyncIt\Domain\SchedulerEvent;

/**
 * Receives lifecycle events from the scheduler.
 *
 * Implementations are notified at batch/flush boundaries (spawn batch,
 * completion batch) — not on every internal tick — to avoid hot-path overhead
 * (per AGENTS.md: "Instrument batch/flush boundaries").
 *
 * @see https://w3id.org/itpack/asyncit-taxonomy#occ-task-spawned
 * @see https://w3id.org/itpack/asyncit-taxonomy#occ-task-resumed
 * @see https://w3id.org/itpack/asyncit-taxonomy#occ-task-completed
 * @api
 */
interface SchedulerEventListenerInterface
{
    public function onEvent(SchedulerEvent $event): void;
}
