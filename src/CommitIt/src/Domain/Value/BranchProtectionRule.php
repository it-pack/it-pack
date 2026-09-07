<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Value;

use InvalidArgumentException;

/**
 * A single rule in a branch-protection policy.
 *
 * Rules match branch names by glob pattern (e.g. `main`, `release/*`, `*-stable`)
 * and specify which mutating operations are permitted for matching branches.
 *
 * @api
 */
final readonly class BranchProtectionRule
{
    public function __construct(
        public readonly string $pattern,
        public readonly bool $allowDelete = false,
        public readonly bool $allowForceCheckout = false,
    ) {
        if ($pattern === '') {
            throw new InvalidArgumentException('Branch protection pattern must not be empty.');
        }

        if (preg_match('/[\x00-\x1f\x7f]/', $pattern) === 1) {
            throw new InvalidArgumentException('Branch protection pattern must not contain control characters.');
        }
    }

    /**
     * Returns true when the given branch name matches this rule's glob pattern.
     *
     * Uses fnmatch semantics: `*` matches any sequence of characters, `?`
     * matches a single character, and the pattern can include path separators.
     */
    public function matches(BranchName $branch): bool
    {
        return fnmatch($this->pattern, $branch->__toString());
    }
}
