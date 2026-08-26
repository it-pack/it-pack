<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract\Port;

/**
 * Vocabulary constants for the task lifecycle status (closed set).
 *
 * The values are the stable string labels; the {@see TaskStatus} enum binds to
 * them. Each case carries a `@see` to its controlled individual in the AsyncIt
 * Scheduling Ontology.
 *
 * @see https://w3id.org/itpack/asyncit#TaskStatus
 * @see https://w3id.org/itpack/asyncit#TaskCreated
 * @see https://w3id.org/itpack/asyncit#TaskRunning
 * @see https://w3id.org/itpack/asyncit#TaskSucceeded
 * @see https://w3id.org/itpack/asyncit#TaskFailed
 * @see https://w3id.org/itpack/asyncit#TaskCancelled
 * @see https://w3id.org/itpack/asyncit#TaskTimedOut
 */
interface TaskStatusVocabulary
{
    public const CREATED = 'created';
    public const RUNNING = 'running';
    public const SUCCEEDED = 'succeeded';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';
    public const TIMED_OUT = 'timed_out';
}
