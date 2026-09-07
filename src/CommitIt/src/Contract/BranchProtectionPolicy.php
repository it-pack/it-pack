<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Contract;

use ItPack\CommitIt\Domain\Value\BranchName;

/**
 * Pluggable policy for branch protection.
 *
 * Implementations decide whether a branch operation (deletion, force-checkout)
 * is permitted for a given branch. The adapter delegates to this port so that
 * protection rules can be customized without modifying adapter internals.
 *
 * @api
 */
interface BranchProtectionPolicy
{
    /**
     * Validates that the named branch may be deleted.
     *
     * @throws Exception\BranchProtectionException When the branch is protected.
     */
    public function validateDeletion(BranchName $branch): void;

    /**
     * Validates that a (possibly forced) checkout of the named branch is permitted.
     *
     * @throws Exception\BranchProtectionException When the checkout is prohibited.
     */
    public function validateCheckout(BranchName $branch): void;
}
