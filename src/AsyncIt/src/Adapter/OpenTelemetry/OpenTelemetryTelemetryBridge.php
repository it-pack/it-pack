<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Adapter\OpenTelemetry;

use ItPack\AsyncIt\Contract\Port\TaskStatus;
use ItPack\AsyncIt\Contract\TelemetryBridgeInterface;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\Context;
use Throwable;

/**
 * OpenTelemetry bridge implementing {@see TelemetryBridgeInterface}.
 *
 * Maps AsyncIt scheduler lifecycle events into OpenTelemetry spans. The bridge
 * is lazy: it materialises the tracer provider only on the first
 * {@see self::record()} call, keeping the OpenTelemetry SDK out of the cold
 * path until telemetry is actually emitted.
 *
 * The bridge creates:
 *
 * - A span on task spawn (`itpack.asyncit.task.spawned`) — ended on terminal transition.
 * - A span event (`itpack.asyncit.task.suspended`) on suspension.
 * - A span event (`itpack.asyncit.task.resumed`) on resumption.
 * - The span ends with status Error on task failure, recording the throwable.
 *
 * Tracer provider is read from {@see Globals} by default; supply a custom
 * provider for testing via the constructor.
 *
 * @see https://w3id.org/itpack/asyncit#RuntimeAdapter
 * @see https://w3id.org/itpack/asyncit-taxonomy#occ-task-spawned
 */
final class OpenTelemetryTelemetryBridge implements TelemetryBridgeInterface
{
    private const string DEFAULT_AWAIT_POINT = 'unknown';

    private const string DEFAULT_STATUS = 'succeeded';

    private const string DEFAULT_TASK_KIND = 'default';

    private bool $initialized = false;

    /**
     * @var array<string, SpanInterface> Active spans keyed by task id, ended on terminal transition.
     */
    private array $spans = [];

    public function __construct(
        private ?TracerProviderInterface $tracerProvider = null,
    ) {
    }

    public function record(string $event, array $context = []): void
    {
        $this->materialize();

        $tracer = $this->tracerProvider?->getTracer('it-pack/async-it');

        if (!$tracer instanceof TracerInterface) {
            return;
        }

        $taskId = is_string($context['taskId'] ?? null) ? $context['taskId'] : 'unknown';

        match ($event) {
            'task.spawned' => $this->onTaskSpawned($tracer, $taskId, $context),
            'task.suspended' => $this->onTaskSuspended($taskId, $context),
            'task.resumed' => $this->onTaskResumed($taskId, $context),
            'task.completed' => $this->onTaskCompleted($taskId, $context),
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

    private function materialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->tracerProvider ??= Globals::tracerProvider();

        $this->initialized = true;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function onTaskSpawned(TracerInterface $tracer, string $taskId, array $context): void
    {
        $status = is_string($context['status'] ?? null) ? $context['status'] : 'running';

        $span = $tracer->spanBuilder('task.' . $status)
            ->setAttribute('itpack.asyncit.task.id', $taskId)
            ->setAttribute('itpack.asyncit.task.kind', $context['taskKind'] ?? self::DEFAULT_TASK_KIND)
            ->setAttribute('itpack.asyncit.execution.id', $context['executionId'] ?? '')
            ->startSpan()
        ;

        Context::storage()->attach(
            Context::getCurrent()->withContextValue($span),
        );

        $this->spans[$taskId] = $span;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function onTaskSuspended(string $taskId, array $context): void
    {
        $span = $this->spans[$taskId] ?? null;
        $span?->addEvent('itpack.asyncit.task.suspended', [
            'awaitPoint' => $context['awaitPoint'] ?? self::DEFAULT_AWAIT_POINT,
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function onTaskResumed(string $taskId, array $context): void
    {
        $span = $this->spans[$taskId] ?? null;
        $span?->addEvent('itpack.asyncit.task.resumed', [
            'awaitPoint' => $context['awaitPoint'] ?? self::DEFAULT_AWAIT_POINT,
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function onTaskCompleted(string $taskId, array $context): void
    {
        $span = $this->spans[$taskId] ?? null;
        if ($span === null) {
            return;
        }

        $status = $context['status'] ?? self::DEFAULT_STATUS;

        if ($status === TaskStatus::Failed->value || $status === TaskStatus::TimedOut->value) {
            $throwable = $context['throwable'] ?? null;
            if ($throwable instanceof Throwable) {
                $span->recordException($throwable);
            }

            $span->setStatus(StatusCode::STATUS_ERROR, $status);
        }

        $duration = $context['duration'] ?? null;

        match (true) {
            $duration instanceof DurationInterface => $span->end((int) ($duration->nanoseconds() / 1_000_000)),
            default => $span->end(),
        };

        unset($this->spans[$taskId]);
    }
}
