<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Behavior\Domain;

/**
 * Links an artifact to the execution that owns it, without holding a mutable
 * reference to the execution session.
 */
trait ExecutionOwnedBehavior
{
    abstract public function executionId(): string;

    // @phpstan-ignore-next-line shipmonk.deadMethod Foundation trait projection; consumed by Milestone 2 artifacts.
    public function belongsTo(string $executionId): bool
    {
        return $this->executionId() === $executionId;
    }
}
