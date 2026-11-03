<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Application;

use ItPack\CommitIt\Behavior\Adapter\ExtendedCommitBehavior;
use ItPack\CommitIt\Contract\ExtendedRepository;
use ItPack\CommitIt\Contract\Repository;
use ItPack\CommitIt\Contract\RepositoryObserver;
use ItPack\CommitIt\Contract\RepositoryOperation;
use ItPack\CommitIt\Contract\WorkingTree;
use ItPack\CommitIt\Domain\Option\BranchQuery;
use ItPack\CommitIt\Domain\Option\CommitOptions;
use ItPack\CommitIt\Domain\Option\TagCleanupOptions;
use ItPack\CommitIt\Domain\Value\BranchInfo;
use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\Commit;
use ItPack\CommitIt\Domain\Value\CommitId;
use ItPack\CommitIt\Domain\Value\CommitMessage;
use ItPack\CommitIt\Domain\Value\RemoteName;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\Domain\Value\TagInfo;
use ItPack\CommitIt\Domain\Value\TagName;
use Throwable;

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
final readonly class ObservedRepository implements ExtendedRepository
{
    use ExtendedCommitBehavior;
    use ObserverDispatch;

    public function __construct(
        private Repository $inner,
        private RepositoryObserver $observer,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function getPath(): RepositoryPath
    {
        return $this->dispatchRun(
            $this->observer,
            RepositoryOperation::GetPath->value,
            fn (): RepositoryPath => $this->inner->getPath(),
        );
    }

    /**
     * @throws Throwable
     */
    public function hasChanges(): bool
    {
        return $this->dispatchRun(
            $this->observer,
            RepositoryOperation::HasChanges->value,
            fn (): bool => $this->inner->hasChanges(),
        );
    }

    /**
     * @throws Throwable
     */
    public function stage(string $file): void
    {
        $this->dispatchRunVoid(
            $this->observer,
            RepositoryOperation::Stage->value,
            fn () => $this->inner->stage($file),
            ['file' => $file],
        );
    }

    /**
     * @throws Throwable
     */
    public function stageAll(): void
    {
        $this->dispatchRunVoid(
            $this->observer,
            RepositoryOperation::StageAll->value,
            fn () => $this->inner->stageAll(),
        );
    }

    /**
     * @throws Throwable
     */
    public function commit(CommitMessage $message, ?CommitOptions $options = null): CommitId
    {
        $context = $options === null
            ? []
            : ['allowEmpty' => $options->allowEmpty, 'amend' => $options->amend];

        return $this->dispatchRun(
            $this->observer,
            RepositoryOperation::Commit->value,
            fn (): CommitId => $this->inner->commit($message, $options),
            $context,
        );
    }

    /**
     * @throws Throwable
     */
    public function getLastCommit(): ?Commit
    {
        return $this->dispatchRun(
            $this->observer,
            RepositoryOperation::GetLastCommit->value,
            fn (): ?Commit => $this->inner->getLastCommit(),
        );
    }

    /**
     * @throws Throwable
     */
    public function getCommit(CommitId $id): Commit
    {
        return $this->dispatchRun(
            $this->observer,
            RepositoryOperation::GetCommit->value,
            fn (): Commit => $this->inner->getCommit($id),
            ['commit' => $id->__toString()],
        );
    }

    /**
     * @throws Throwable
     */
    public function createBranch(BranchName $name): void
    {
        $this->dispatchRunVoid(
            $this->observer,
            RepositoryOperation::CreateBranch->value,
            fn () => $this->inner->createBranch($name),
            ['branch' => $name->__toString()],
        );
    }

    /**
     * @throws Throwable
     */
    public function checkoutBranch(BranchName $name): void
    {
        $this->dispatchRunVoid(
            $this->observer,
            RepositoryOperation::CheckoutBranch->value,
            fn () => $this->inner->checkoutBranch($name),
            ['branch' => $name->__toString()],
        );
    }

    /**
     * @throws Throwable
     */
    public function deleteBranch(BranchName $name): void
    {
        $this->dispatchRunVoid(
            $this->observer,
            RepositoryOperation::DeleteBranch->value,
            fn () => $this->inner->deleteBranch($name),
            ['branch' => $name->__toString()],
        );
    }

    /**
     * @return list<BranchName>
     * @throws Throwable
     */
    public function listBranches(): array
    {
        return $this->dispatchRun(
            $this->observer,
            RepositoryOperation::ListBranches->value,
            fn (): array => $this->inner->listBranches(),
        );
    }

    /**
     * @throws Throwable
     */
    public function getBranchInfo(BranchName $name): ?BranchInfo
    {
        return $this->dispatchRun(
            $this->observer,
            RepositoryOperation::GetBranchInfo->value,
            fn (): ?BranchInfo => $this->inner->getBranchInfo($name),
            ['branch' => $name->__toString()],
        );
    }

    /**
     * @return list<BranchInfo>
     * @throws Throwable
     */
    public function searchBranches(BranchQuery $query): array
    {
        return $this->dispatchRun(
            $this->observer,
            RepositoryOperation::SearchBranches->value,
            fn (): array => $this->inner->searchBranches($query),
            ['pattern' => $query->pattern, 'currentOnly' => $query->currentOnly, 'remoteOnly' => $query->remoteOnly],
        );
    }

    /**
     * @throws Throwable
     */
    public function createTag(TagName $name, ?CommitId $target = null): void
    {
        $context = ['tag' => $name->__toString()];

        if ($target !== null) {
            $context['target'] = $target->__toString();
        }

        $this->dispatchRunVoid(
            $this->observer,
            RepositoryOperation::CreateTag->value,
            fn () => $this->inner->createTag($name, $target),
            $context,
        );
    }

    /**
     * @throws Throwable
     */
    public function deleteTag(TagName $name): void
    {
        $this->dispatchRunVoid(
            $this->observer,
            RepositoryOperation::DeleteTag->value,
            fn () => $this->inner->deleteTag($name),
            ['tag' => $name->__toString()],
        );
    }

    /**
     * @return list<TagName>
     * @throws Throwable
     */
    public function listTags(): array
    {
        return $this->dispatchRun(
            $this->observer,
            RepositoryOperation::ListTags->value,
            fn (): array => $this->inner->listTags(),
        );
    }

    /**
     * @throws Throwable
     */
    public function getTagInfo(TagName $name): ?TagInfo
    {
        return $this->dispatchRun(
            $this->observer,
            RepositoryOperation::GetTagInfo->value,
            fn (): ?TagInfo => $this->inner->getTagInfo($name),
            ['tag' => $name->__toString()],
        );
    }

    /**
     * @return list<TagName>
     * @throws Throwable
     */
    public function cleanupTags(TagCleanupOptions $options): array
    {
        return $this->dispatchRun(
            $this->observer,
            RepositoryOperation::CleanupTags->value,
            fn (): array => $this->inner->cleanupTags($options),
            ['pattern' => $options->pattern, 'force' => $options->force],
        );
    }

    /**
     * @throws Throwable
     */
    public function addRemote(RemoteName $name, string $url): void
    {
        $this->dispatchRunVoid(
            $this->observer,
            RepositoryOperation::AddRemote->value,
            fn () => $this->inner->addRemote($name, $url),
            ['remote' => $name->__toString()],
        );
    }

    /**
     * @throws Throwable
     */
    public function removeRemote(RemoteName $name): void
    {
        $this->dispatchRunVoid(
            $this->observer,
            RepositoryOperation::RemoveRemote->value,
            fn () => $this->inner->removeRemote($name),
            ['remote' => $name->__toString()],
        );
    }

    /**
     * @return array<string, string>
     * @throws Throwable
     */
    public function listRemotes(): array
    {
        return $this->dispatchRun(
            $this->observer,
            RepositoryOperation::ListRemotes->value,
            fn (): array => $this->inner->listRemotes(),
        );
    }

    /**
     * @throws Throwable
     */
    public function fetch(?RemoteName $remote = null): void
    {
        $this->dispatchRunVoid(
            $this->observer,
            RepositoryOperation::Fetch->value,
            fn () => $this->inner->fetch($remote),
        );
    }

    /**
     * @throws Throwable
     */
    public function pull(?RemoteName $remote = null, ?BranchName $branch = null): void
    {
        $this->dispatchRunVoid(
            $this->observer,
            RepositoryOperation::Pull->value,
            fn () => $this->inner->pull($remote, $branch),
        );
    }

    /**
     * @throws Throwable
     */
    public function push(?RemoteName $remote = null, ?BranchName $branch = null): void
    {
        $this->dispatchRunVoid(
            $this->observer,
            RepositoryOperation::Push->value,
            fn () => $this->inner->push($remote, $branch),
        );
    }

    public function getWorkingTree(): WorkingTree
    {
        return new ObservedWorkingTree($this->inner->getWorkingTree(), $this->observer);
    }

    /**
     * @param list<string> $args
     * @throws \Throwable
     */
    public function execute(string $command, array $args = []): string
    {
        return $this->dispatchRun(
            $this->observer,
            RepositoryOperation::Execute->value,
            fn (): string => $this->inner->execute($command, $args),
            ['command' => $command],
        );
    }
}
