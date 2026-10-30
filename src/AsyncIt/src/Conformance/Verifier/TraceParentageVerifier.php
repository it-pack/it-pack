<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Conformance\Verifier;

use ItPack\AsyncIt\Conformance\Contract\VerifierInterface;
use ItPack\AsyncIt\Conformance\Fixture\AsyncRuntimeFixtureInterface;
use ItPack\AsyncIt\Conformance\Result\ConformanceResult;
use ItPack\AsyncIt\Conformance\Result\ConformanceViolation;
use ItPack\AsyncIt\Conformance\Result\ViolationSeverity;
use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\Port\ViolationVocabulary;
use ItPack\AsyncIt\Contract\SchedulerInterface;
use ItPack\AsyncIt\SDK\Support\LazyTelemetryBridge;

/**
 * Conformance Assertion E — Trace Parentage.
 *
 * Proves the runtime's telemetry bridge records lifecycle events with parent-child
 * linkage through a real suspend/resume cycle: a parent task spawns a child task
 * and awaits it. The runtime emits `task.spawned`, `task.suspended`,
 * `task.resumed`, and `task.completed` events through its listener pipeline into
 * the telemetry bridge. The verifier reads the bridge's recorded events and
 * asserts that the child's spawn event carries the parent's task id as `parentId`.
 *
 * When the fixture cannot expose a {@see LazyTelemetryBridge} (which provides
 * `events()` for introspection), the assertion is reported as fixture-unsupported
 * so it does not falsely pass.
 *
 * @see https://w3id.org/itpack/asyncit#RuntimeAdapter
 * @see https://w3id.org/itpack/asyncit-taxonomy#occ-task-spawned
 * @api
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

        $bridge = $fixture->telemetry();

        if (!$bridge instanceof LazyTelemetryBridge) {
            return $result->add(new ConformanceViolation(
                ViolationVocabulary::FIXTURE_UNSUPPORTED,
                'TraceParentageVerifier: fixture does not expose a LazyTelemetryBridge '
                . 'with events() for introspection.',
                ViolationSeverity::Warning,
            ));
        }

        $scheduler = $fixture->scheduler();

        if (!$scheduler instanceof EngineSchedulerInterface) {
            return $result->add(new ConformanceViolation(
                ViolationVocabulary::FIXTURE_UNSUPPORTED,
                'TraceParentageVerifier: fixture scheduler does not implement EngineSchedulerInterface.',
                ViolationSeverity::Warning,
            ));
        }

        $parentTask = $scheduler->spawn(function () use ($scheduler): string {
            $childTask = $scheduler->spawn(static fn (): string => 'child-result');

            $result = $scheduler->await($childTask);
            assert(is_string($result));

            return $result;
        });

        $scheduler->run();

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

        $parentId = $parentTask->id()->value;

        $childSpawn = array_find(
            $spawnEvents,
            static fn (array $e): bool => ($e['context']['parentId'] ?? null) === $parentId,
        );

        if ($childSpawn === null) {
            $result = $result->add(new ConformanceViolation(
                ViolationVocabulary::TELEMETRY_SPAN_BROKEN_PARENTAGE,
                'No spawn event for child task carrying parentId = parent was recorded.',
            ));
        }

        return $result;
    }
}
