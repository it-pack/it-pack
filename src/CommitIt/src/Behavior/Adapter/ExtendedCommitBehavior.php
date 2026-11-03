<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Behavior\Adapter;

use ItPack\CommitIt\Domain\Option\CommitOptions;
use ItPack\CommitIt\Domain\Value\CommitId;
use ItPack\CommitIt\Domain\Value\CommitMessage;

trait ExtendedCommitBehavior
{
    abstract public function stage(string $file): void;

    abstract public function stageAll(): void;

    abstract public function commit(CommitMessage $message, ?CommitOptions $options = null): CommitId;

    public function stageAndCommit(string $file, CommitMessage $message, ?CommitOptions $options = null): CommitId
    {
        $this->stage($file);

        return $this->commit($message, $options);
    }

    public function stageAllAndCommit(CommitMessage $message, ?CommitOptions $options = null): CommitId
    {
        $this->stageAll();

        return $this->commit($message, $options);
    }
}
