<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Adapter\Reporting;

use ItPack\CommandIt\Contract\Port\ExecutionReporterInterface;

/**
 * Execution reporter that discards everything it is given.
 *
 * Useful as a disabled-instrumentation default and as a test double where
 * emission must not be observed. It never throws and retains nothing.
 *
 * @api
 */
final class NullExecutionReporter implements ExecutionReporterInterface
{
    public function reportObservation(string $message, array $context = []): void
    {
    }

    public function reportOccurrence(string $type, array $context = []): void
    {
    }

    public function reportMeasurement(string $name, int|float $value, string $unit): void
    {
    }

    public function beginActivity(string $name): string
    {
        return '';
    }

    public function endActivity(string $activityId): void
    {
    }
}
