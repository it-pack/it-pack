<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Contract;

use ItPack\CommitIt\Domain\Option\CommitOptions;
use ItPack\CommitIt\Domain\Value\Commit;
use ItPack\CommitIt\Domain\Value\CommitId;
use ItPack\CommitIt\Domain\Value\CommitMessage;
use ItPack\CommitIt\Domain\Value\RepositoryPath;

/**
 * Commit, staging, and low-level repository read operations.
 *
 * @api
 */
interface CommitPort
{
    public function getPath(): RepositoryPath;

    public function hasChanges(): bool;

    public function stage(string $file): void;

    public function stageAll(): void;

    public function commit(CommitMessage $message, ?CommitOptions $options = null): CommitId;

    public function getLastCommit(): ?Commit;

    public function getCommit(CommitId $id): Commit;

    /**
     * Generic git command passthrough (git-specific, NOT portable).
     *
     * @param list<string> $args
     */
    public function execute(string $command, array $args = []): string;
}
