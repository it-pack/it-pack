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
 * critical observation documenting it and lets the runtime auto-resolve the activity.
 * The operation-derived terminal status is preserved; use an explicit policy to alter
 * the terminal outcome.
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
                    self::OPEN_ACTIVITY_MESSAGE_PREFIX . ' and was auto-resolved.',
                    $activity->definition()->name()->toString(),
                ),
                ObservationSeverity::Critical,
            );
        }
    }
}
