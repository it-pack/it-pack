<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Behavior;

use ItPack\RunIt\Contract\Domain\ActivityIdInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use OpenTelemetry\API\Trace\SpanContextInterface;

trait ExecutionSpanStoreBehavior
{
    private const string PREFIX_ACTIVITY_ID = 'a:';

    private const string PREFIX_EXECUTION_ID = 'e:';

    /** @var array<string, SpanContextInterface> */
    private array $contexts = [];

    private static function activityKey(string|ActivityIdInterface $activityId): string
    {
        return self::PREFIX_ACTIVITY_ID . $activityId;
    }

    private static function executionKey(string|ExecutionIdInterface $executionId): string
    {
        return self::PREFIX_EXECUTION_ID . $executionId;
    }

    public function recordExecution(string|ExecutionIdInterface $executionId, SpanContextInterface $context): void
    {
        $this->record(self::executionKey($executionId), $context);
    }

    public function recordActivity(string|ActivityIdInterface $activityId, SpanContextInterface $context): void
    {
        $this->record(self::activityKey($activityId), $context);
    }

    public function executionContext(string|ExecutionIdInterface $executionId): ?SpanContextInterface
    {
        return $this->contexts[self::executionKey($executionId)] ?? null;
    }

    public function activityContext(string|ActivityIdInterface $activityId): ?SpanContextInterface
    {
        return $this->contexts[self::activityKey($activityId)] ?? null;
    }

    abstract private function record(string $key, SpanContextInterface $context): void;
}
