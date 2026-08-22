<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Application;

use ItPack\CommitIt\Contract\Repository;
use ItPack\CommitIt\Contract\RepositoryObserver;
use ItPack\CommitIt\Contract\WorkingTree;
use ItPack\CommitIt\Domain\Option\CommitOptions;
use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\Commit;
use ItPack\CommitIt\Domain\Value\CommitId;
use ItPack\CommitIt\Domain\Value\CommitMessage;
use ItPack\CommitIt\Domain\Value\RemoteName;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\Domain\Value\TagName;

/**
 * Non-intrusive decorator that dispatches {@see RepositoryObserver} lifecycle
 * events around every repository operation.
 *
 * Observation never changes the result of the wrapped operation: on failure the
 * original {@see \Throwable} is always rethrown, and any observer exception is
 * caught and discarded on a separate, non-recursive path so the observer can
 * neither trigger further observed operations nor mask a real failure.
 *
 * Structured context deliberately omits commit-message bodies and any secret
 * material; it carries only operation-relevant, non-sensitive identifiers.
 *
 * @api
 */
final class ObservedRepository implements Repository
{
    public function __construct(
        private readonly Repository $inner,
        private readonly RepositoryObserver $observer,
    ) {
    }

    public function getPath(): RepositoryPath
    {
        return $this->run('getPath', fn (): RepositoryPath => $this->inner->getPath());
    }

    public function hasChanges(): bool
    {
        return $this->run('hasChanges', fn (): bool => $this->inner->hasChanges());
    }

    public function stage(string $file): void
    {
        $this->runVoid('stage', fn () => $this->inner->stage($file), ['file' => $file]);
    }

    public function stageAll(): void
    {
        $this->runVoid('stageAll', fn () => $this->inner->stageAll());
    }

    public function commit(CommitMessage $message, ?CommitOptions $options = null): CommitId
    {
        $context = $options === null
            ? []
            : ['allowEmpty' => $options->allowEmpty, 'amend' => $options->amend];

        return $this->run(
            'commit',
            fn (): CommitId => $this->inner->commit($message, $options),
            $context,
        );
    }

    public function getLastCommit(): ?Commit
    {
        return $this->run('getLastCommit', fn (): ?Commit => $this->inner->getLastCommit());
    }

    public function getCommit(CommitId $id): Commit
    {
        return $this->run(
            'getCommit',
            fn (): Commit => $this->inner->getCommit($id),
            ['commit' => $id->__toString()],
        );
    }

    public function createBranch(BranchName $name): void
    {
        $this->runVoid('createBranch', fn () => $this->inner->createBranch($name), ['branch' => $name->__toString()]);
    }

    public function checkoutBranch(BranchName $name): void
    {
        $this->runVoid('checkoutBranch', fn () => $this->inner->checkoutBranch($name), ['branch' => $name->__toString()]);
    }

    public function deleteBranch(BranchName $name): void
    {
        $this->runVoid('deleteBranch', fn () => $this->inner->deleteBranch($name), ['branch' => $name->__toString()]);
    }

    /**
     * @return list<BranchName>
     */
    public function listBranches(): array
    {
        return $this->run('listBranches', fn (): array => $this->inner->listBranches());
    }

    public function createTag(TagName $name, ?CommitId $target = null): void
    {
        $context = ['tag' => $name->__toString()];

        if ($target !== null) {
            $context['target'] = $target->__toString();
        }

        $this->runVoid('createTag', fn () => $this->inner->createTag($name, $target), $context);
    }

    public function deleteTag(TagName $name): void
    {
        $this->runVoid('deleteTag', fn () => $this->inner->deleteTag($name), ['tag' => $name->__toString()]);
    }

    /**
     * @return list<TagName>
     */
    public function listTags(): array
    {
        return $this->run('listTags', fn (): array => $this->inner->listTags());
    }

    public function addRemote(RemoteName $name, string $url): void
    {
        $this->runVoid('addRemote', fn () => $this->inner->addRemote($name, $url), ['remote' => $name->__toString()]);
    }

    public function removeRemote(RemoteName $name): void
    {
        $this->runVoid('removeRemote', fn () => $this->inner->removeRemote($name), ['remote' => $name->__toString()]);
    }

    /**
     * @return array<string, string>
     */
    public function listRemotes(): array
    {
        return $this->run('listRemotes', fn (): array => $this->inner->listRemotes());
    }

    public function fetch(?RemoteName $remote = null): void
    {
        $this->runVoid('fetch', fn () => $this->inner->fetch($remote));
    }

    public function pull(?RemoteName $remote = null, ?BranchName $branch = null): void
    {
        $this->runVoid('pull', fn () => $this->inner->pull($remote, $branch));
    }

    public function push(?RemoteName $remote = null, ?BranchName $branch = null): void
    {
        $this->runVoid('push', fn () => $this->inner->push($remote, $branch));
    }

    public function getWorkingTree(): WorkingTree
    {
        return $this->inner->getWorkingTree();
    }

    /**
     * @param list<string> $args
     */
    public function execute(string $command, array $args = []): string
    {
        return $this->run('execute', fn (): string => $this->inner->execute($command, $args), ['command' => $command]);
    }

    /**
     * @template T
     * @param callable(): T $action
     * @return T
     */
    /**
     * @template T
     * @param callable(): T $action
     * @param array<string, mixed> $context
     * @return T
     */
    private function run(string $operation, callable $action, array $context = [])
    {
        $this->notify(fn () => $this->observer->onStart($operation, $context));

        try {
            $result = $action();
        } catch (\Throwable $error) {
            $this->notify(fn () => $this->observer->onFailure($operation, $error, $context));

            throw $error;
        }

        $this->notify(fn () => $this->observer->onSuccess($operation, $context));

        return $result;
    }

    /**
     * Void-returning variant of {@see self::run} so the generic never resolves to
     * `void` (which would make `return $result` illegal for non-void operations).
     *
     * @param callable(): void $action
     */
    /**
     * @param array<string, mixed> $context
     */
    private function runVoid(string $operation, callable $action, array $context = []): void
    {
        $this->notify(fn () => $this->observer->onStart($operation, $context));

        try {
            $action();
        } catch (\Throwable $error) {
            $this->notify(fn () => $this->observer->onFailure($operation, $error, $context));

            throw $error;
        }

        $this->notify(fn () => $this->observer->onSuccess($operation, $context));
    }

    /**
     * Routes observer dispatch failures to a separate, non-recursive path: an
     * observer must never re-observe nor alter the wrapped operation's outcome.
     */
    private function notify(callable $dispatch): void
    {
        try {
            $dispatch();
        } catch (\Throwable) {
            // Observer failures are intentionally swallowed, not reobserved.
        }
    }
}
