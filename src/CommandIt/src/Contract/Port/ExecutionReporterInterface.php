<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Port;

/**
 * Rich reporter port: lifecycle reporting plus observations and activity boundaries.
 *
 * Extends {@see LifecycleReporterInterface} so any full reporter satisfies the narrow
 * core contract — the coordinator and executor depend only on the lifecycle subset and
 * still accept a full reporter (a structural superset). Occurrence types follow the
 * It_Pack convention: stable, lower-case, versioned, and independent of any PHP class
 * name (see {@see LifecycleReporterInterface} for the `command.*.v1` vocabulary).
 *
 * A reporter must never replace a thrown business exception with its own failure;
 * the executor is responsible for preserving the original {@see \Throwable}.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Observation
 * @see https://w3id.org/itpack/runit#Occurrence
 */
interface ExecutionReporterInterface extends LifecycleReporterInterface
{
    /**
     * Records a qualitative observation.
     *
     * @param array<string, mixed> $context
     */
    public function reportObservation(string $message, array $context = []): void;

    /**
     * Begins a bounded unit of work and returns an activity token used to end it.
     */
    public function beginActivity(string $name): string;

    /**
     * Ends the activity identified by the token returned from {@see self::beginActivity()}.
     */
    public function endActivity(string $activityId): void;
}
