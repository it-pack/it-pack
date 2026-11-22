<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Contract;

use OpenTelemetry\API\Trace\SpanContextInterface;

/**
 * Remembers the OpenTelemetry span context produced for each RunIt execution and
 * activity so that child spans can be wired to the correct parent.
 *
 * A default store belongs to one publisher; an injected store may be shared.
 * Retention and concurrency guarantees depend on the implementation. The default
 * in-memory store evicts old contexts, so a parent lookup can return null even if
 * the parent was previously recorded. Injected stores define their own policy.
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
