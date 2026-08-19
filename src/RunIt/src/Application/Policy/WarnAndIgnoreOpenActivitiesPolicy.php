<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Policy;

use ItPack\RunIt\Contract\Application\ExecutionCompletionPolicyInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;

/**
 * Open-activity policy that records an informational observation and otherwise ignores
 * activities still open at completion.
 *
 * The activity runtime closes activities it opened, so by completion the open set is
 * typically empty. When an activity is nonetheless still open, this policy records an
 * informational observation and lets the runtime auto-resolve the activity.
 *
 * @see https://w3id.org/itpack/runit#Activity
 */
final class WarnAndIgnoreOpenActivitiesPolicy implements ExecutionCompletionPolicyInterface
{
    public function resolveOpenActivities(ExecutionScopeInterface $scope, array $openActivities): void
    {
        foreach ($openActivities as $activity) {
            $scope->observations()->record(
                sprintf(
                    'Activity "%s" was still open at execution completion and was ignored.',
                    $activity->definition()->name()->toString(),
                ),
                ObservationSeverity::Info,
            );
        }
    }
}
