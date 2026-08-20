<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Contract;

use OpenTelemetry\API\Trace\SpanContextInterface;

/**
 * Remembers the OpenTelemetry span context produced for each RunIt execution and
 * activity so that child spans can be wired to the correct parent.
 *
 * The store is scoped to a single {@see \ItPack\RunIt\Adapter\OpenTelemetry\TelemetryPublisher}
 * instance and is intentionally bounded in practice: an execution's span context
 * is only needed while its activities are being mapped, after which it can be
 * forgotten. Implementations may evict entries to bound memory.
 *
 * @api
 */
interface ExecutionSpanStoreInterface
{
    public function recordExecution(string $executionId, SpanContextInterface $context): void;

    public function recordActivity(string $activityId, SpanContextInterface $context): void;

    public function executionContext(string $executionId): ?SpanContextInterface;

    public function activityContext(string $activityId): ?SpanContextInterface;
}
