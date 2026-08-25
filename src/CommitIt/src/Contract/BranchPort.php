<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Contract;

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
}
