<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Adapter\Prometheus;

use Closure;
use ItPack\AsyncIt\Contract\TelemetryBridgeInterface;
use ItPack\AsyncIt\Domain\Behavior\Lazy;
use ItPack\RunIt\Contract\Domain\DurationInterface;

/**
 * Prometheus metrics bridge implementing {@see TelemetryBridgeInterface}.
 *
 * Maps scheduler lifecycle events into Prometheus metrics via
 * `prom/phprometheus` (available in `suggest`). The bridge is lazy and only
 * materialises the registry on the first {@see self::record()} call.
 *
 * Metrics exposed:
 *
 * - **Gauge** `asyncit_tasks_runnable` — currently runnable tasks.
 * - **Gauge** `asyncit_handles_active` — currently registered poll handles.
 * - **Gauge** `asyncit_timers_active` — currently active timers.
 * - **Counter** `asyncit_tasks_completed_total` — tasks completed by final status (label: status).
 * - **Histogram** `asyncit_task_duration_seconds` — task duration.
 * - **Histogram** `asyncit_poll_wait_seconds` — reactor poll wait time.
 * - **Histogram** `asyncit_schedule_to_dispatch_seconds` — time from spawn to first dispatch.
 *
 * Labels use closed taxonomies only (TaskStatus values) — never user-controlled
 * values (AGENS.md: "Do not use user-controlled values as metric names").
 *
 * Requires `prom/phprometheus` in the consumer's dependency set; this class
 * performs a runtime `class_exists` guard so construction without the package
 * is a no-op.
 *
 * @see https://w3id.org/itpack/asyncit#RuntimeAdapter
 * @see https://w3id.org/itpack/asyncit-taxonomy#ms-task-duration
 */
final class PrometheusTelemetryBridge implements TelemetryBridgeInterface
{
    use Lazy;

    private const string DEFAULT_STATUS = 'succeeded';

    private const string METRIC_TASKS_RUNNABLE = 'asyncit_tasks_runnable';

    private const string METRIC_HANDLES_ACTIVE = 'asyncit_handles_active';

    private const string METRIC_TIMERS_ACTIVE = 'asyncit_timers_active';

    private const string METRIC_TASKS_COMPLETED_TOTAL = 'asyncit_tasks_completed_total';

    private const string METRIC_TASK_DURATION_SECONDS = 'asyncit_task_duration_seconds';

    private const string METRIC_POLL_WAIT_SECONDS = 'asyncit_poll_wait_seconds';

    private const string METRIC_SCHEDULE_TO_DISPATCH_SECONDS = 'asyncit_schedule_to_dispatch_seconds';

    /**
     * @var array<string, object>|null Lazily-created prometheus instruments.
     */
    private ?array $instruments = null;

    /**
     * @param (Closure(): object)|null $registryFactory builds the prometheus registry on first record
     */
    public function __construct(
        private readonly ?Closure $registryFactory = null,
    ) {
    }

    public function record(string $event, array $context = []): void
    {
        $this->materialize(function (): void {
            $this->initialized = true;

            if (!class_exists('Promphp\\Prometheus\\RegistryInterface')) {
                $this->instruments = null;

                return;
            }

            $factory = $this->registryFactory
                ?? static function (): object {
                    $class = 'Promphp\\Prometheus\\InMemory\\InMemoryRegistry';

                    /** @var object $registry */
                    $registry = new $class();

                    return $registry;
                };

            $registry = $factory();

            /** @var object $adapter */
            $adapter = method_exists($registry, 'getAdapter')
                ? $registry->getAdapter()
                : $registry;

            $this->instruments = $this->buildInstruments($adapter);
        });

        if ($this->instruments === null) {
            return;
        }

        match ($event) {
            'task.spawned' => $this->recordGauge(self::METRIC_TASKS_RUNNABLE, 1),
            'task.completed' => $this->onTaskCompleted($context),
            'poll.wait' => $this->recordHistogram(
                self::METRIC_POLL_WAIT_SECONDS,
                is_numeric($context['duration'] ?? 0) ? (float) ($context['duration'] ?? 0) : 0.0,
            ),
            'schedule.dispatch' => $this->recordHistogram(
                self::METRIC_SCHEDULE_TO_DISPATCH_SECONDS,
                is_numeric($context['duration'] ?? 0) ? (float) ($context['duration'] ?? 0) : 0.0,
            ),
            default => null,
        };
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * @return list<array{event: string, context: array<string, mixed>}>
     */
    public function events(): array
    {
        return [];
    }

    /**
     * @phpstan-ignore-next-line
     */
    private function buildInstruments(object $adapter): array
    {
        return [
            self::METRIC_TASKS_RUNNABLE => $adapter->registerGauge(
                '',
                self::METRIC_TASKS_RUNNABLE,
                'Currently runnable tasks',
            ),
            self::METRIC_HANDLES_ACTIVE => $adapter->registerGauge(
                '',
                self::METRIC_HANDLES_ACTIVE,
                'Currently registered poll handles',
            ),
            self::METRIC_TIMERS_ACTIVE => $adapter->registerGauge(
                '',
                self::METRIC_TIMERS_ACTIVE,
                'Currently active timers',
            ),
            self::METRIC_TASKS_COMPLETED_TOTAL => $adapter->registerCounter(
                '',
                self::METRIC_TASKS_COMPLETED_TOTAL,
                'Tasks completed by status',
                ['status'],
            ),
            self::METRIC_TASK_DURATION_SECONDS => $adapter->registerHistogram(
                '',
                self::METRIC_TASK_DURATION_SECONDS,
                'Task duration in seconds',
                [0.001, 0.005, 0.01, 0.05, 0.1, 0.5, 1, 5, 10],
            ),
            self::METRIC_POLL_WAIT_SECONDS => $adapter->registerHistogram(
                '',
                self::METRIC_POLL_WAIT_SECONDS,
                'Reactor poll wait time in seconds',
                [0.001, 0.005, 0.01, 0.05, 0.1, 0.5, 1],
            ),
            self::METRIC_SCHEDULE_TO_DISPATCH_SECONDS => $adapter->registerHistogram(
                '',
                self::METRIC_SCHEDULE_TO_DISPATCH_SECONDS,
                'Time from task spawn to first dispatch in seconds',
                [0.001, 0.005, 0.01, 0.05, 0.1, 0.5, 1, 5],
            ),
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function onTaskCompleted(array $context): void
    {
        $this->recordGauge(self::METRIC_TASKS_RUNNABLE, -1);

        $status = $context['status'] ?? self::DEFAULT_STATUS;

        /** @phpstan-ignore-next-line */
        $this->instruments[self::METRIC_TASKS_COMPLETED_TOTAL]->incBy(1, [$status]);

        $duration = $context['duration'] ?? null;
        $seconds = $duration instanceof DurationInterface
            ? $duration->nanoseconds() / 1_000_000_000
            : 0.0;

        /** @phpstan-ignore-next-line */
        $this->instruments[self::METRIC_TASK_DURATION_SECONDS]->observe($seconds);
    }

    private function recordGauge(string $name, int $delta): void
    {
        /** @phpstan-ignore-next-line */
        $this->instruments[$name]?->incBy($delta);
    }

    /**
     * @param array<string>|null $labels
     */
    private function recordHistogram(string $name, float|int $durationNs, ?array $labels = null): void
    {
        $histogram = $this->instruments[$name] ?? null;

        if ($histogram === null) {
            return;
        }

        $seconds = (float) $durationNs / 1_000_000_000;

        if ($labels !== null) {
            /** @phpstan-ignore-next-line */
            $histogram->observe($seconds, $labels);

            return;
        }

        /** @phpstan-ignore-next-line */
        $histogram->observe($seconds);
    }
}
