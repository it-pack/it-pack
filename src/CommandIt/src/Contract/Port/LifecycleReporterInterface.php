<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Port;

/**
 * Minimal lifecycle-reporting seam consumed by the core execution pipeline.
 *
 * The coordinator and executor depend only on this subset: they emit stable
 * occurrences and quantitative measurements. The richer {@see ExecutionReporterInterface}
 * adds observations and activity boundaries that serve adapters and future model legs
 * (Activities); segregating the port (ISP) lets core consumers accept the narrow
 * contract while still receiving a full reporter, which is a structural superset.
 *
 * Occurrence types follow the It_Pack convention: stable, lower-case, versioned, and
 * independent of any PHP class name. The three lifecycle occurrences are
 * {@see self::COMMAND_RECEIVED}, {@see self::COMMAND_HANDLED}, and
 * {@see self::COMMAND_FAILED}.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Observation
 * @see https://w3id.org/itpack/runit#Occurrence
 */
interface LifecycleReporterInterface
{
    /**
     * @see https://w3id.org/itpack/runit#Occurrence Stable, versioned occurrence type for a received command.
     */
    public const string COMMAND_RECEIVED = 'command.received.v1';

    /**
     * @see https://w3id.org/itpack/runit#Occurrence Stable, versioned occurrence type for a handled (succeeded) command.
     */
    public const string COMMAND_HANDLED = 'command.handled.v1';

    /**
     * @see https://w3id.org/itpack/runit#Occurrence Stable, versioned occurrence type for a failed command.
     */
    public const string COMMAND_FAILED = 'command.failed.v1';

    /**
     * Stable occurrence-context keys. Use these instead of inline literals so the
     * published vocabulary stays constant-backed and greppable.
     */
    public const string CONTEXT_EXECUTION_ID = 'execution_id';
    public const string CONTEXT_COMMAND = 'command';
    public const string CONTEXT_STATUS = 'status';
    public const string CONTEXT_ERROR_CLASS = 'error_class';
    public const string CONTEXT_ERROR_MESSAGE = 'error_message';

    /**
     * Stable measurement identity for the command duration.
     */
    public const string MEASUREMENT_DURATION = 'duration_ms';
    public const string DURATION_UNIT_MS = 'ms';

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
}
