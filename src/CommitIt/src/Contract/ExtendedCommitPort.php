<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Contract;

use ItPack\CommitIt\Domain\Option\CommitOptions;
use ItPack\CommitIt\Domain\Value\Commit;
use ItPack\CommitIt\Domain\Value\CommitId;
use ItPack\CommitIt\Domain\Value\CommitMessage;

/**
 * Commit, staging, and low-level repository read operations.
 *
 * @api
 */
interface ExtendedCommitPort extends CommitPort
{
    public function stageAndCommit(string $file, CommitMessage $message, ?CommitOptions $options = null): CommitId;

    public function stageAllAndCommit(CommitMessage $message, ?CommitOptions $options = null): CommitId;
}
