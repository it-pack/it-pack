<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Artifact;

/**
 * Vocabulary constants for scheduler lifecycle artifact kinds.
 *
 * These provide stable string identifiers for artifacts published by the
 * AsyncIt scheduler into the RunIt artifact stream, covering task spawn,
 * suspension, resumption, and terminal transitions.
 *
 * @see https://w3id.org/itpack/asyncit#Execution
 * @see https://w3id.org/itpack/asyncit-taxonomy#occ-task-spawned
 * @see https://w3id.org/itpack/asyncit-taxonomy#occ-task-resumed
 * @see https://w3id.org/itpack/asyncit-taxonomy#occ-task-completed
 */
interface ArtifactVocabulary
{
    public const KIND_SCHEDULER_FAILURE = 'asyncit.scheduler.failure';

    public const KIND_TASK_SPAWNED = 'itpack.asyncit.task.spawned.v1';

    public const KIND_TASK_RESUMED = 'itpack.asyncit.task.resumed.v1';

    public const KIND_TASK_COMPLETED = 'itpack.asyncit.task.completed.v1';

    public const KIND_SCHEDULER_STARVATION = 'itpack.asyncit.observation.scheduler.starvation.v1';

    public const KIND_QUEUE_PRESSURE = 'itpack.asyncit.observation.queue.pressure.v1';

    public const KIND_HANDLE_LEAK = 'itpack.asyncit.observation.handle.leak.v1';
}
