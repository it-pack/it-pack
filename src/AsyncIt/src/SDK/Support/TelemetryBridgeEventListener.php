<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\SDK\Support;

use ItPack\AsyncIt\Contract\Port\SchedulerEventType;
use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\SchedulerEventInterface;
use ItPack\AsyncIt\Contract\SchedulerEventListenerInterface;
use ItPack\AsyncIt\Contract\TelemetryBridgeInterface;

/**
 * Bridges scheduler lifecycle events into the telemetry bridge.
 *
 * Converts {@see SchedulerEventInterface} instances into
 * {@see TelemetryBridgeInterface::record()} calls with structured context
 * suitable for span linkage and duration correlation.
 *
 * @see https://w3id.org/itpack/asyncit#Task
 * @see https://w3id.org/itpack/asyncit-taxonomy#occ-task-spawned
 * @api
 */
final class TelemetryBridgeEventListener implements SchedulerEventListenerInterface
{
    private readonly TelemetryBridgeInterface $bridge;

    /**
     * @param array<string, mixed> $staticContext
     */
    public function __construct(
        TelemetryBridgeInterface $bridge,
        private readonly array $staticContext = [],
    ) {
        $this->bridge = $bridge;
    }

    public function onEvent(SchedulerEventInterface $event): void
    {
        $this->bridge->record($event->type->value, $this->buildContext($event));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(SchedulerEventInterface $event): array
    {
        $context = $this->staticContext;

        $context['taskId'] = $event->task->id()->value;

        if ($event->parentId !== null) {
            $context['parentId'] = $event->parentId->value;
        }

        if ($event->type === SchedulerEventType::Completed) {
            $context['status'] = match ($event->status) {
                TaskStatus::Succeeded => 'succeeded',
                TaskStatus::Failed => 'failed',
                TaskStatus::Cancelled => 'cancelled',
                TaskStatus::TimedOut => 'timed_out',
                default => $event->status->name,
            };
        }

        if ($event->awaitPoint !== null) {
            $context['awaitPoint'] = $event->awaitPoint->name;
        }

        return $context;
    }
}
