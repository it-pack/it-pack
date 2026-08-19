<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Policy;

use ItPack\RunIt\Contract\Application\ExecutionCompletionPolicyInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;

/**
 * Open-activity policy that escalates the execution when activities are still open.
 *
 * The policy never changes the original business result or exception; instead it records
 * a critical observation so the open activities are surfaced as an execution-level
 * failure signal for downstream health reporting.
 *
 * @see https://w3id.org/itpack/runit#Activity
 */
final class FailExecutionOnOpenActivitiesPolicy implements ExecutionCompletionPolicyInterface
{
    public function resolveOpenActivities(ExecutionScopeInterface $scope, array $openActivities): void
    {
        foreach ($openActivities as $activity) {
            $scope->observations()->record(
                sprintf(
                    'Activity "%s" was still open at execution completion; the execution is flagged as failed.',
                    $activity->definition()->name()->toString(),
                ),
                ObservationSeverity::Critical,
            );
        }
    }
}
