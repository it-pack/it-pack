<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\RunIt\Contract\Domain\DurationInterface;

/**
 * Contract for a durable task definition that survives process restarts.
 *
 * Only task *definitions* are serialized — never suspended Fibers or raw
 * closures. The operation is a job class name string plus a payload array,
 * making it safe to persist in Redis, PostgreSQL, or RunIt's artifact store.
 * On restart, pending definitions are rehydrated into the scheduler.
 *
 * @see https://w3id.org/itpack/asyncit#PersistentTask
 * @see https://w3id.org/itpack/runt-taxonomy#occ-task-rehydrated
 */
interface PersistentTaskInterface extends ValueObjectInterface
{
    public function id(): TaskIdInterface;

    /**
     * Returns the fully-qualified job class name to instantiate.
     * Never a raw closure — only serializable class names are permitted.
     */
    public function operation(): string;

    /**
     * Returns the constructor payload for the job class, or an empty array.
     *
     * @return array<string, mixed>
     */
    public function payload(): array;

    /**
     * Returns the optional monotonic deadline after which the task expires.
     */
    public function deadline(): ?DurationInterface;

    /**
     * Returns the task's current lifecycle status.
     */
    public function status(): TaskStatus;
}
