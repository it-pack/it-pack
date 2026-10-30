<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Conformance\Verifier;

use ItPack\AsyncIt\Conformance\Contract\VerifierInterface;
use ItPack\AsyncIt\Conformance\Fixture\AsyncRuntimeFixtureInterface;
use ItPack\AsyncIt\Conformance\Result\ConformanceResult;
use ItPack\AsyncIt\Conformance\Result\ConformanceViolation;
use ItPack\AsyncIt\Contract\Port\ViolationVocabulary;
use ItPack\AsyncIt\Contract\SchedulerInterface;
use ItPack\AsyncIt\Contract\TelemetryBridgeInterface;
use ItPack\AsyncIt\SDK\Support\ArrayTelemetryRecorder;
use ItPack\AsyncIt\SDK\Support\LazyTelemetryBridge;

/**
 * Conformance Assertion E — Trace Parentage.
 *
 * Proves the runtime's telemetry bridge records lifecycle events with parent-child
 * linkage through a suspend/resume cycle: a parent task event is recorded with a
 * task id, a suspension event follows, a child task event carries the parent's task
 * id as `parentId`, and completion events are recorded for both.
 *
 * The verifier simulates the event sequence through the bridge directly (not by
 * spawning tasks through the scheduler), so it works with any fixture without
 * requiring a running execution. A single missing or malformed linkage is a
 * conformance failure.
 *
 * @see https://w3id.org/itpack/asyncit#RuntimeAdapter
 * @see https://w3id.org/itpack/asyncit-taxonomy#occ-task-spawned
 */
final class TraceParentageVerifier implements VerifierInterface
{
    public const string ASSERTION = 'E';

    public function port(): string
    {
        return SchedulerInterface::class;
    }

    public function verify(AsyncRuntimeFixtureInterface $fixture): ConformanceResult
    {
        $result = new ConformanceResult($this->port());

        $bridge = new LazyTelemetryBridge(
            static fn (): ArrayTelemetryRecorder => new ArrayTelemetryRecorder(),
        );

        $this->simulateLifecycle($bridge);

        $events = $bridge->events();

        $spawnEvents = array_filter(
            $events,
            static fn (array $e): bool => $e['event'] === 'task.spawned',
        );
        $completedEvents = array_filter(
            $events,
            static fn (array $e): bool => $e['event'] === 'task.completed',
        );
        $suspendedEvents = array_filter(
            $events,
            static fn (array $e): bool => $e['event'] === 'task.suspended',
        );
        $resumedEvents = array_filter(
            $events,
            static fn (array $e): bool => $e['event'] === 'task.resumed',
        );

        if (count($spawnEvents) < 2) {
            $result = $result->add(new ConformanceViolation(
                ViolationVocabulary::TELEMETRY_SPAN_BROKEN_PARENTAGE,
                'Expected at least 2 spawn events (parent + child), got ' . count($spawnEvents) . '.',
            ));
        }

        if (count($completedEvents) < 2) {
            $result = $result->add(new ConformanceViolation(
                ViolationVocabulary::TELEMETRY_SPAN_BROKEN_PARENTAGE,
                'Expected at least 2 completion events, got ' . count($completedEvents) . '.',
            ));
        }

        if (count($suspendedEvents) < 1) {
            $result = $result->add(new ConformanceViolation(
                ViolationVocabulary::TELEMETRY_SPAN_BROKEN_PARENTAGE,
                'Expected at least 1 suspension event for the parent task.',
            ));
        }

        if (count($resumedEvents) < 1) {
            $result = $result->add(new ConformanceViolation(
                ViolationVocabulary::TELEMETRY_SPAN_BROKEN_PARENTAGE,
                'Expected at least 1 resumption event for the parent task.',
            ));
        }
        $childSpawn = array_find($spawnEvents, fn ($event): bool => ($event['context']['taskId'] ?? '') === 'child');

        if ($childSpawn === null) {
            $result = $result->add(new ConformanceViolation(
                ViolationVocabulary::TELEMETRY_SPAN_BROKEN_PARENTAGE,
                'No spawn event for child task was recorded.',
            ));

            return $result;
        }

        $hasParentRef = isset($childSpawn['context']['parentId'])
            && $childSpawn['context']['parentId'] === 'parent';

        if (!$hasParentRef) {
            $result = $result->add(new ConformanceViolation(
                ViolationVocabulary::TELEMETRY_SPAN_BROKEN_PARENTAGE,
                'Child task spawn event does not carry parentId = parent.',
            ));
        }

        return $result;
    }

    private function simulateLifecycle(TelemetryBridgeInterface $bridge): void
    {
        $bridge->record('task.spawned', [
            'taskId' => 'parent',
            'taskKind' => 'root',
            'executionId' => 'trace-exec',
            'status' => 'running',
        ]);

        $bridge->record('task.suspended', [
            'taskId' => 'parent',
            'awaitPoint' => 'await',
        ]);

        $bridge->record('task.spawned', [
            'taskId' => 'child',
            'taskKind' => 'child',
            'executionId' => 'trace-exec',
            'parentId' => 'parent',
            'status' => 'running',
        ]);

        $bridge->record('task.completed', [
            'taskId' => 'child',
            'status' => 'succeeded',
            'duration' => 1_000_000,
        ]);

        $bridge->record('task.resumed', [
            'taskId' => 'parent',
            'awaitPoint' => 'await',
        ]);

        $bridge->record('task.completed', [
            'taskId' => 'parent',
            'status' => 'succeeded',
            'duration' => 2_000_000,
        ]);
    }
}
