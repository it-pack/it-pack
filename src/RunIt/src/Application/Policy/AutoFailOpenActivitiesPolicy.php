<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Policy;

use ItPack\RunIt\Contract\Application\ExecutionCompletionPolicyInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;

/**
 * Default policy for open activities at execution completion.
 *
 * The activity runtime closes activities it opened, so by completion the open set is
 * typically empty. When an activity is nonetheless still open, this policy records a
 * warning observation documenting it and lets the runtime auto-resolve the activity.
 * Stricter policies (fail-execution, auto-cancel) refine this in a later milestone.
 *
 * @see https://w3id.org/itpack/runit#Activity
 */
final class AutoFailOpenActivitiesPolicy implements ExecutionCompletionPolicyInterface
{
    public function resolveOpenActivities(ExecutionScopeInterface $scope, array $openActivities): void
    {
        foreach ($openActivities as $activity) {
            $scope->observations()->record(
                sprintf(
                    'Activity "%s" was still open at execution completion and was auto-resolved.',
                    $activity->definition()->name()->toString(),
                ),
                ObservationSeverity::Warning,
            );
        }
    }
}
