<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application;

use ItPack\RunIt\Contract\Domain\ActivityInterface;

/**
 * Contract for resolving open activities when an execution completes.
 *
 * At completion, any activity still running is handed to the policy. The default
 * policy records a diagnostic observation and lets the activity runtime auto-resolve
 * the activity; stricter policies may fail the execution. The policy never changes
 * the original business result or exception.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Activity
 */
interface ExecutionCompletionPolicyInterface
{
    /**
     * Shared message prefix for the diagnostic observation recorded when open
     * activities are handed to a policy at execution completion. Implementations
     * append their resolution-specific tail and sprintf the activity name into
     * `%s`, keeping the shared wording byte-identical across policies.
     */
    public const string OPEN_ACTIVITY_MESSAGE_PREFIX = 'Activity "%s" was still open at execution completion';

    /**
     * Resolves open activities at execution completion.
     *
     * @param list<ActivityInterface> $openActivities
     */
    public function resolveOpenActivities(ExecutionScopeInterface $scope, array $openActivities): void;
}
