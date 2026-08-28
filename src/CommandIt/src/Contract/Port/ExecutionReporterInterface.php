<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Port;

/**
 * Port that records the observable behavior of a command execution.
 *
 * Occurrence types follow the It_Pack convention: stable, lower-case, versioned,
 * and independent of any PHP class name. The three lifecycle occurrences are
 * {@see self::COMMAND_RECEIVED}, {@see self::COMMAND_HANDLED}, and
 * {@see self::COMMAND_FAILED}.
 *
 * A reporter must never replace a thrown business exception with its own failure;
 * the executor is responsible for preserving the original {@see \Throwable}.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Observation
 * @see https://w3id.org/itpack/runit#Occurrence
 */
interface ExecutionReporterInterface
{
    /**
     * @see https://w3id.org/itpack/runit#Occurrence Stable, versioned occurrence type for a received command.
     */
    public const COMMAND_RECEIVED = 'command.received.v1';

    /**
     * @see https://w3id.org/itpack/runit#Occurrence Stable, versioned occurrence type for a handled (succeeded) command.
     */
    public const COMMAND_HANDLED = 'command.handled.v1';

    /**
     * @see https://w3id.org/itpack/runit#Occurrence Stable, versioned occurrence type for a failed command.
     */
    public const COMMAND_FAILED = 'command.failed.v1';

    /**
     * Stable occurrence-context keys. Use these instead of inline literals so the
     * published vocabulary stays constant-backed and greppable.
     */
    public const CONTEXT_EXECUTION_ID = 'execution_id';
    public const CONTEXT_COMMAND = 'command';
    public const CONTEXT_STATUS = 'status';
    public const CONTEXT_ERROR_CLASS = 'error_class';
    public const CONTEXT_ERROR_MESSAGE = 'error_message';

    /**
     * Stable measurement identity for the command duration.
     */
    public const MEASUREMENT_DURATION = 'duration_ms';
    public const DURATION_UNIT_MS = 'ms';

    /**
     * Records a qualitative observation.
     *
     * @param array<string, mixed> $context
     */
    public function reportObservation(string $message, array $context = []): void;

    /**
     * Records an immutable occurrence of a stable type.
     *
     * @param array<string, mixed> $context
     */
    public function reportOccurrence(string $type, array $context = []): void;

    /**
     * Records a quantitative measurement.
     */
    public function reportMeasurement(string $name, int|float $value, string $unit): void;

    /**
     * Begins a bounded unit of work and returns an activity token used to end it.
     */
    public function beginActivity(string $name): string;

    /**
     * Ends the activity identified by the token returned from {@see self::beginActivity()}.
     */
    public function endActivity(string $activityId): void;
}
