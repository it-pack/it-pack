<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Service;

use ItPack\CommitIt\Contract\BranchProtectionPolicy;
use ItPack\CommitIt\Domain\Exception\BranchProtectionException;
use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\BranchProtectionRule;

/**
 * Default {@see BranchProtectionPolicy} that enforces rules defined as glob
 * patterns (e.g. `main`, `release/*`) with per-rule allow-flags.
 *
 * When no rules match a branch, operations are permitted. This preserves
 * backward compatibility: repositories without protection rules behave exactly
 * as before.
 *
 * @api
 */
final readonly class DefaultBranchProtectionPolicy implements BranchProtectionPolicy
{
    /**
     * @param list<BranchProtectionRule> $rules
     */
    public function __construct(
        private array $rules = [],
    ) {
    }

    public function validateDeletion(BranchName $branch): void
    {
        foreach ($this->rules as $rule) {
            if (!$rule->allowDelete && $rule->matches($branch)) {
                throw new BranchProtectionException(
                    sprintf('Branch "%s" is protected and cannot be deleted.', $branch->__toString()),
                );
            }
        }
    }

    public function validateCheckout(BranchName $branch): void
    {
        foreach ($this->rules as $rule) {
            if (!$rule->allowForceCheckout && $rule->matches($branch)) {
                throw new BranchProtectionException(
                    sprintf('Force checkout of protected branch "%s" is not permitted.', $branch->__toString()),
                );
            }
        }
    }
}
