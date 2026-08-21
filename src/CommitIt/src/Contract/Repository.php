<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Contract;

use ItPack\CommitIt\Domain\Option\CommitOptions;
use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\Commit;
use ItPack\CommitIt\Domain\Value\CommitId;
use ItPack\CommitIt\Domain\Value\CommitMessage;
use ItPack\CommitIt\Domain\Value\RemoteName;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\Domain\Value\TagName;

/**
 * Vendor-neutral repository operations port.
 *
 * Every operation happens on a repository. References (branch/tag/remote names)
 * are validated value objects, never raw strings. The {@see execute()} method is
 * a generic git command passthrough and is intentionally NON-portable; prefer
 * the dedicated methods and only fall back to {@see execute()} when none apply.
 *
 * @api
 */
interface Repository
{
    public function getPath(): RepositoryPath;

    public function hasChanges(): bool;

    public function stage(string $file): void;

    public function stageAll(): void;

    public function commit(CommitMessage $message, ?CommitOptions $options = null): CommitId;

    public function getLastCommit(): ?Commit;

    public function getCommit(CommitId $id): Commit;

    public function createBranch(BranchName $name): void;

    public function checkoutBranch(BranchName $name): void;

    public function deleteBranch(BranchName $name): void;

    /**
     * @return list<BranchName>
     */
    public function listBranches(): array;

    public function createTag(TagName $name, ?CommitId $target = null): void;

    public function deleteTag(TagName $name): void;

    /**
     * @return list<TagName>
     */
    public function listTags(): array;

    public function addRemote(RemoteName $name, string $url): void;

    public function removeRemote(RemoteName $name): void;

    /**
     * @return array<string, string> map of remote name => url
     */
    public function listRemotes(): array;

    public function fetch(?RemoteName $remote = null): void;

    public function pull(?RemoteName $remote = null, ?BranchName $branch = null): void;

    public function push(?RemoteName $remote = null, ?BranchName $branch = null): void;

    public function getWorkingTree(): WorkingTree;

    /**
     * Generic git command passthrough (git-specific, NOT portable).
     *
     * @param list<string> $args
     */
    public function execute(string $command, array $args = []): string;
}
