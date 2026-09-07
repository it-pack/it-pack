<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Value;

/**
 * Immutable metadata describing a branch.
 *
 * @api
 */
final readonly class BranchInfo
{
    public function __construct(
        public BranchName $name,
        public CommitId $commitId,
        public bool $isCurrent,
        public bool $isRemote,
        public ?string $upstream = null,
    ) {
    }
}
