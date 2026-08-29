<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Value;

/**
 * Immutable description of a checked-out worktree.
 *
 * @api
 */
final readonly class WorktreeInfo
{
    public function __construct(
        public readonly string $path,
        public readonly ?string $branch,
        public readonly bool $isMain,
    ) {
    }
}
