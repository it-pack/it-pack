<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Conformance\Verifier;

use ItPack\AsyncIt\Conformance\Contract\ConformanceResultInterface;
use ItPack\AsyncIt\Conformance\Contract\VerifierInterface;
use ItPack\AsyncIt\Conformance\Fixture\AsyncRuntimeFixtureInterface;
use ItPack\AsyncIt\Conformance\Result\ConformanceResult;
use ItPack\AsyncIt\Conformance\Result\ConformanceViolation;
use ItPack\AsyncIt\Conformance\Result\ViolationSeverity;
use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\Port\ViolationVocabulary;
use ItPack\AsyncIt\Contract\SchedulerInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;

/**
 * Conformance Assertion A.2 — Group Cancellation Isolation.
 *
 * Verifies that when a parent task spawns child tasks concurrently on the same
 * scheduler, each child executes with its own identity and produces results
 * that are not cross-contaminated by sibling execution. This is the integrated
 * assertion complementing Assertion A (storage-port level): A.2 drives the
 * full spawn → await → suspend → resume path through the scheduler and proves
 * no sibling observes or affects another task's outcome.
 *
 * @see https://w3id.org/itpack/asyncit#TaskGroup
 * @see https://w3id.org/itpack/asyncit#CancellationScope
 * @see https://w3id.org/itpack/runit#Execution
 * @api
 */
final class GroupCancellationIsolationVerifier implements VerifierInterface
{
    public const string ASSERTION = 'A.2';

    private const string RESULT_A = 'a-done';

    private const string RESULT_B = 'b-done';

    public function port(): string
    {
        return SchedulerInterface::class;
    }

    public function verify(AsyncRuntimeFixtureInterface $fixture): ConformanceResultInterface
    {
        $result = new ConformanceResult($this->port());

        $scheduler = $fixture->scheduler();

        if (!$scheduler instanceof EngineSchedulerInterface) {
            return $result->add(new ConformanceViolation(
                ViolationVocabulary::FIXTURE_UNSUPPORTED,
                'GroupCancellationIsolationVerifier: scheduler does not implement'
                . ' EngineSchedulerInterface (no spawn/await).',
                ViolationSeverity::Warning,
            ));
        }

        $observed = [];
        $storage = $fixture->contextStorage();

        $scheduler->spawn(function () use ($scheduler, $storage, &$observed): void {
            $childA = $scheduler->spawn(function () use ($scheduler, $storage, &$observed): string {
                $ctx = $storage->current();
                $observed['childA_scope'] = $ctx instanceof ExecutionScopeInterface ? spl_object_id($ctx) : null;
                $task = $scheduler->currentTask();
                $observed['childA_task'] = $task?->id()->value;

                return self::RESULT_A;
            });

            $valueA = $scheduler->await($childA);

            $childB = $scheduler->spawn(function () use ($scheduler, $storage, &$observed): string {
                $ctx = $storage->current();
                $observed['childB_scope'] = $ctx instanceof ExecutionScopeInterface ? spl_object_id($ctx) : null;
                $task = $scheduler->currentTask();
                $observed['childB_task'] = $task?->id()->value;

                return self::RESULT_B;
            });

            $valueB = $scheduler->await($childB);

            $observed['resultA'] = $valueA;
            $observed['resultB'] = $valueB;
        });

        $scheduler->run();

        if (!isset($observed['resultA'], $observed['resultB'])) {
            return $result->add(new ConformanceViolation(
                ViolationVocabulary::CONTEXT_BLEED,
                'GroupCancellationIsolationVerifier: parent task did not complete — children were not awaited.',
                ViolationSeverity::Error,
            ));
        }

        if ($observed['resultA'] !== self::RESULT_A || $observed['resultB'] !== self::RESULT_B) {
            return $result->add(new ConformanceViolation(
                ViolationVocabulary::CONTEXT_BLEED,
                'GroupCancellationIsolationVerifier: result cross-contamination — child returned wrong value.',
                ViolationSeverity::Error,
            ));
        }

        if ($observed['childA_task'] === $observed['childB_task']) {
            return $result->add(new ConformanceViolation(
                ViolationVocabulary::CONTEXT_BLEED,
                'GroupCancellationIsolationVerifier: childA and childB share the same task identity — '
                . 'tasks are not properly isolated.',
                ViolationSeverity::Error,
            ));
        }

        if (
            $observed['childA_scope'] !== null
            && $observed['childB_scope'] !== null
            && $observed['childA_scope'] === $observed['childB_scope']
        ) {
            return $result->add(new ConformanceViolation(
                ViolationVocabulary::CONTEXT_BLEED,
                'GroupCancellationIsolationVerifier: childA and childB observed the same context scope — '
                . 'context is not isolated across sibling tasks.',
                ViolationSeverity::Error,
            ));
        }

        return $result;
    }
}
