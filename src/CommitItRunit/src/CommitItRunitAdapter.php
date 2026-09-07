<?php

declare(strict_types=1);

namespace ItPack\CommitItRunit;

use Closure;
use ItPack\CommitItRunit\Model\Activity;
use ItPack\CommitItRunit\Model\Occurrence;
use ItPack\CommitItRunit\Model\Snapshot;
use ItPack\CommitIt\Contract\RepositoryObserver;

/**
 * RunIt observer adapter for CommitIt.
 *
 * Maps CommitIt repository operations to RunIt's Activity/Observation/Occurrence
 * triad:
 *
 * - {@see onStart}   emits an Activity marking the operation begun.
 * - {@see onSuccess} emits a Snapshot as a measurement of successful outcome.
 * - {@see onFailure} emits an Occurrence recording the failure.
 *
 * The adapter preserves the observer-failure isolation contract of CommitIt: any
 * exception thrown by the RunIt integration is caught and discarded so that
 * observation never alters or masks the wrapped operation's outcome.
 *
 * @api
 */
final class CommitItRunitAdapter implements RepositoryObserver
{
    /** @var non-empty-string|null */
    private ?string $activityId = null;

    /**
     * @param Closure(Activity): void $activityEmitter   Emits an Activity.
     * @param Closure(Snapshot): void $snapshotEmitter   Emits a successful Snapshot.
     * @param Closure(Occurrence): void $occurrenceEmitter Emits a failure Occurrence.
     */
    public function __construct(
        private readonly Closure $activityEmitter,
        private readonly Closure $snapshotEmitter,
        private readonly Closure $occurrenceEmitter,
    ) {
    }

    public function onStart(string $operation, array $context = []): void
    {
        $this->dispatchFailureSafe(function () use ($operation, $context): void {
            $this->activityId = uniqid('commitit-', true);
            $activity = new Activity(
                name: $this->normalizeOperationName($operation),
                context: $this->enrichContext($context),
                activityId: $this->activityId,
            );
            ($this->activityEmitter)($activity);
        });
    }

    public function onSuccess(string $operation, array $context = []): void
    {
        $this->dispatchFailureSafe(function () use ($operation, $context): void {
            $snapshot = new Snapshot(
                name: $this->normalizeOperationName($operation),
                context: $this->enrichContext($context),
                activityId: $this->activityId ?? uniqid('commitit-', true),
            );
            ($this->snapshotEmitter)($snapshot);
        });
    }

    public function onFailure(string $operation, \Throwable $error, array $context = []): void
    {
        $this->dispatchFailureSafe(function () use ($operation, $error, $context): void {
            $occurrence = new Occurrence(
                name: $this->normalizeOperationName($operation),
                context: $this->enrichContext($context),
                error: [
                    'class'   => $error::class,
                    'message' => $error->getMessage(),
                    'code'    => $error->getCode(),
                ],
                activityId: $this->activityId ?? uniqid('commitit-', true),
            );
            ($this->occurrenceEmitter)($occurrence);
        });
    }

    /**
     * Maps CommitIt operation names to RunIt-style dotted identifiers.
     *
     * @return non-empty-string
     */
    private function normalizeOperationName(string $operation): string
    {
        return match ($operation) {
            'getPath'         => 'repository.get_path',
            'hasChanges'      => 'repository.has_changes',
            'stage'           => 'repository.stage',
            'stageAll'        => 'repository.stage_all',
            'commit'          => 'repository.commit',
            'getLastCommit'   => 'repository.get_last_commit',
            'getCommit'       => 'repository.get_commit',
            'createBranch'    => 'reference.create_branch',
            'checkoutBranch'  => 'reference.checkout_branch',
            'deleteBranch'    => 'reference.delete_branch',
            'listBranches'    => 'reference.list_branches',
            'createTag'       => 'reference.create_tag',
            'deleteTag'       => 'reference.delete_tag',
            'listTags'        => 'reference.list_tags',
            'addRemote'       => 'remote.add',
            'removeRemote'    => 'remote.remove',
            'listRemotes'     => 'remote.list',
            'fetch'           => 'remote.fetch',
            'pull'            => 'remote.pull',
            'push'            => 'remote.push',
            'execute'         => 'repository.execute',
            'listWorktrees'   => 'worktree.list',
            'add'             => 'worktree.add',
            'remove'          => 'worktree.remove',
            default           => 'repository.' . $operation,
        };
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function enrichContext(array $context): array
    {
        return $context + [
            'adapter' => 'CommitIt-RunIt',
            'model'   => 'Activity/Snapshot/Occurrence',
        ];
    }

    private function dispatchFailureSafe(callable $dispatch): void
    {
        try {
            $dispatch();
        } catch (\Throwable) {
            // Observer failures are intentionally swallowed on a non-recursive path.
        }
    }
}
