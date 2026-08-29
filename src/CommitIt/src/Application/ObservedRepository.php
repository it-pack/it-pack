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
final readonly class ObservedRepository implements Repository
{
    use ObserverDispatch;

    public function __construct(
        private readonly Repository $inner,
        private readonly RepositoryObserver $observer,
    ) {
    }

    public function getPath(): RepositoryPath
    {
        return $this->dispatchRun($this->observer, 'getPath', fn (): RepositoryPath => $this->inner->getPath());
    }

    public function hasChanges(): bool
    {
        return $this->dispatchRun($this->observer, 'hasChanges', fn (): bool => $this->inner->hasChanges());
    }

    public function stage(string $file): void
    {
        $this->dispatchRunVoid($this->observer, 'stage', fn () => $this->inner->stage($file), ['file' => $file]);
    }

    public function stageAll(): void
    {
        $this->dispatchRunVoid($this->observer, 'stageAll', fn () => $this->inner->stageAll());
    }

    public function commit(CommitMessage $message, ?CommitOptions $options = null): CommitId
    {
        $context = $options === null
            ? []
            : ['allowEmpty' => $options->allowEmpty, 'amend' => $options->amend];

        return $this->dispatchRun(
            $this->observer,
            'commit',
            fn (): CommitId => $this->inner->commit($message, $options),
            $context,
        );
    }

    public function getLastCommit(): ?Commit
    {
        return $this->dispatchRun($this->observer, 'getLastCommit', fn (): ?Commit => $this->inner->getLastCommit());
    }

    public function getCommit(CommitId $id): Commit
    {
        return $this->dispatchRun(
            $this->observer,
            'getCommit',
            fn (): Commit => $this->inner->getCommit($id),
            ['commit' => $id->__toString()],
        );
    }

    public function createBranch(BranchName $name): void
    {
        $this->dispatchRunVoid(
            $this->observer,
            'createBranch',
            fn () => $this->inner->createBranch($name),
            ['branch' => $name->__toString()],
        );
    }

    public function checkoutBranch(BranchName $name): void
    {
        $this->dispatchRunVoid(
            $this->observer,
            'checkoutBranch',
            fn () => $this->inner->checkoutBranch($name),
            ['branch' => $name->__toString()],
        );
    }

    public function deleteBranch(BranchName $name): void
    {
        $this->dispatchRunVoid(
            $this->observer,
            'deleteBranch',
            fn () => $this->inner->deleteBranch($name),
            ['branch' => $name->__toString()],
        );
    }

    /**
     * @return list<BranchName>
     */
    public function listBranches(): array
    {
        return $this->dispatchRun($this->observer, 'listBranches', fn (): array => $this->inner->listBranches());
    }

    public function createTag(TagName $name, ?CommitId $target = null): void
    {
        $context = ['tag' => $name->__toString()];

        if ($target !== null) {
            $context['target'] = $target->__toString();
        }

        $this->dispatchRunVoid(
            $this->observer,
            'createTag',
            fn () => $this->inner->createTag($name, $target),
            $context,
        );
    }

    public function deleteTag(TagName $name): void
    {
        $this->dispatchRunVoid(
            $this->observer,
            'deleteTag',
            fn () => $this->inner->deleteTag($name),
            ['tag' => $name->__toString()],
        );
    }

    /**
     * @return list<TagName>
     */
    public function listTags(): array
    {
        return $this->dispatchRun($this->observer, 'listTags', fn (): array => $this->inner->listTags());
    }

    public function addRemote(RemoteName $name, string $url): void
    {
        $this->dispatchRunVoid(
            $this->observer,
            'addRemote',
            fn () => $this->inner->addRemote($name, $url),
            ['remote' => $name->__toString()],
        );
    }

    public function removeRemote(RemoteName $name): void
    {
        $this->dispatchRunVoid(
            $this->observer,
            'removeRemote',
            fn () => $this->inner->removeRemote($name),
            ['remote' => $name->__toString()],
        );
    }

    /**
     * @return array<string, string>
     */
    public function listRemotes(): array
    {
        return $this->dispatchRun($this->observer, 'listRemotes', fn (): array => $this->inner->listRemotes());
    }

    public function fetch(?RemoteName $remote = null): void
    {
        $this->dispatchRunVoid($this->observer, 'fetch', fn () => $this->inner->fetch($remote));
    }

    public function pull(?RemoteName $remote = null, ?BranchName $branch = null): void
    {
        $this->dispatchRunVoid($this->observer, 'pull', fn () => $this->inner->pull($remote, $branch));
    }

    public function push(?RemoteName $remote = null, ?BranchName $branch = null): void
    {
        $this->dispatchRunVoid($this->observer, 'push', fn () => $this->inner->push($remote, $branch));
    }

    public function getWorkingTree(): WorkingTree
    {
        return new ObservedWorkingTree($this->inner->getWorkingTree(), $this->observer);
    }

    /**
     * @param list<string> $args
     */
    public function execute(string $command, array $args = []): string
    {
        return $this->dispatchRun(
            $this->observer,
            'execute',
            fn (): string => $this->inner->execute($command, $args),
            ['command' => $command],
        );
    }
}
