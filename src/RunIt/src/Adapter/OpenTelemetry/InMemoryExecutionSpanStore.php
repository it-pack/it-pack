<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry;

use ItPack\RunIt\Adapter\OpenTelemetry\Contract\ExecutionSpanStoreInterface;
use OpenTelemetry\API\Trace\SpanContextInterface;

/**
 * In-memory {@see ExecutionSpanStoreInterface} implementation.
 *
 * It keeps the OpenTelemetry span context of every mapped RunIt execution and
 * activity so the bridge can parent activities and child executions correctly
 * within a single publisher instance. The map is intentionally simple; callers
 * that publish executions and activities in correlated order get correct span
 * parenting. The store never retains live spans, only their lightweight context.
 *
 * @see https://opentelemetry.io/
 */
final class InMemoryExecutionSpanStore implements ExecutionSpanStoreInterface
{
    /** @var array<string, SpanContextInterface> */
    private array $executions = [];

    /** @var array<string, SpanContextInterface> */
    private array $activities = [];

    public function recordExecution(string $executionId, SpanContextInterface $context): void
    {
        $this->executions[$executionId] = $context;
    }

    public function recordActivity(string $activityId, SpanContextInterface $context): void
    {
        $this->activities[$activityId] = $context;
    }

    public function executionContext(string $executionId): ?SpanContextInterface
    {
        return $this->executions[$executionId] ?? null;
    }

    public function activityContext(string $activityId): ?SpanContextInterface
    {
        return $this->activities[$activityId] ?? null;
    }
}
