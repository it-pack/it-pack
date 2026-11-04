<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Behavior\Adapter;

use ItPack\CommitIt\Domain\Option\CommitOptions;
use ItPack\CommitIt\Domain\Value\CommitId;
use ItPack\CommitIt\Domain\Value\CommitMessage;
use Throwable;

trait ExtendedCommitBehavior
{
    abstract public function stage(string $file): void;

    abstract public function stageAll(): void;

    abstract public function commit(CommitMessage $message, ?CommitOptions $options = null): CommitId;

    /**
     * @throws Throwable
     */
    public function stageAndCommit(string $file, CommitMessage $message, ?CommitOptions $options = null): CommitId
    {
        $this->stage($file);

        return $this->commit($message, $options);
    }

    /**
     * @throws Throwable
     */
    public function stageAllAndCommit(CommitMessage $message, ?CommitOptions $options = null): CommitId
    {
        $this->stageAll();

        return $this->commit($message, $options);
    }
}
