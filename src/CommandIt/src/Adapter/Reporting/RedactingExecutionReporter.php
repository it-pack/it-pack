<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Adapter\Reporting;

use ItPack\CommandIt\Contract\Port\ExecutionReporterInterface;
use ItPack\CommandIt\Contract\Port\RedactionPolicyInterface;

/**
 * Decorates an {@see ExecutionReporterInterface}, redacting sensitive context
 * before it reaches the delegate.
 *
 * Observations and occurrences carry arbitrary caller context, so their context
 * arrays are passed through the {@see RedactionPolicyInterface} first. Measurements
 * are numeric and activities are internal bookkeeping, so they are forwarded
 * unchanged. This keeps secret/oversized/high-cardinality data out of whatever
 * external sink the delegate publishes to.
 *
 * @api
 */
final class RedactingExecutionReporter implements ExecutionReporterInterface
{
    public function __construct(
        private ExecutionReporterInterface $delegate,
        private RedactionPolicyInterface $policy,
    ) {
    }

    public function reportObservation(string $message, array $context = []): void
    {
        $this->delegate->reportObservation($message, $this->policy->redact($context));
    }

    public function reportOccurrence(string $type, array $context = []): void
    {
        $this->delegate->reportOccurrence($type, $this->policy->redact($context));
    }

    public function reportMeasurement(string $name, int|float $value, string $unit): void
    {
        $this->delegate->reportMeasurement($name, $value, $unit);
    }

    public function beginActivity(string $name): string
    {
        return $this->delegate->beginActivity($name);
    }

    public function endActivity(string $activityId): void
    {
        $this->delegate->endActivity($activityId);
    }
}
