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
        public string $path,
        public ?string $branch,
        public bool $isMain,
        public bool $orphaned = false,
    ) {
    }
}
