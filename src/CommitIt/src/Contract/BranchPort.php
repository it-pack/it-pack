<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Contract;

use ItPack\CommitIt\Domain\Option\BranchQuery;
use ItPack\CommitIt\Domain\Value\BranchInfo;
use ItPack\CommitIt\Domain\Value\BranchName;

/**
 * Branch operations.
 *
 * @api
 */
interface BranchPort
{
    public function createBranch(BranchName $name): void;

    public function checkoutBranch(BranchName $name): void;

    public function deleteBranch(BranchName $name): void;

    /**
     * @return list<BranchName>
     */
    public function listBranches(): array;

    /**
     * Returns rich metadata for the given branch, or null when it does not exist.
     */
    public function getBranchInfo(BranchName $name): ?BranchInfo;

    /**
     * Searches branches matching the given query criteria.
     *
     * @return list<BranchInfo>
     */
    public function searchBranches(BranchQuery $query): array;
}
