<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Scheduler;

use ItPack\AsyncIt\Contract\CapabilityDescriptorInterface;
use ItPack\AsyncIt\Contract\Port\StreamPollHandleInterface;
use ItPack\AsyncIt\Contract\TaskInterface;

/**
 * Scheduler introspection and registration surface.
 *
 * Exposes scheduler-level capabilities, stream registration, and task record
 * introspection that are not part of the core {@see EngineSchedulerInterface}.
 *
 * Backs {@see FiberTaskScheduler} methods that are not part of the core
 * scheduler contract.
 *
 * @see https://w3id.org/itpack/asyncit#Scheduler
 * @api
 */
interface SchedulerIntrospectionInterface
{
    /**
     * Returns the negotiated capability descriptor, or null when not yet
     * negotiated.
     */
    public function capabilities(): ?CapabilityDescriptorInterface;

    /**
     * Registers a stream for poll-readiness tracking and returns its handle.
     */
    public function registerStream(mixed $stream): StreamPollHandleInterface;

    /**
     * Returns the current task records tracked by the reactor.
     *
     * @return array<string, TaskRecordInterface>
     */
    public function taskRecords(): array;

    /**
     * Awaits the result of any of the supplied tasks, returning the id of the
     * first to complete.
     *
     * @param list<TaskInterface> $tasks
     */
    public function awaitAny(array $tasks): string;
}
