<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use ItPack\RunIt\Contract\Application\ExecutionCompletionPolicyInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Domain\ActivityInterface;
use RuntimeException;

/**
 * Completion policy that always throws, for exercising open-activity resolution failures.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class ThrowingCompletionPolicy implements ExecutionCompletionPolicyInterface
{
    /**
     * @param list<ActivityInterface> $openActivities
     */
    public function resolveOpenActivities(ExecutionScopeInterface $scope, array $openActivities): void
    {
        throw new RuntimeException('completion policy is broken');
    }
}
