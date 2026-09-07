<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Adapter\RunIt;

use ItPack\CommitIt\Contract\RepositoryObserver;
use ItPack\CommitIt\Domain\Value\RepositoryPath;

/**
 * CommitIt adapter for RunIt execution model.
 *
 * Maps CommitIt repository operations to RunIt's Activity/Measurement/Occurrence/Snapshot triad.
 * This adapter enables CommitIt to integrate with the RunIt execution framework while
 * maintaining the ports-not-imports architectural principle.
 *
 * @api
 */
final readonly class CommitItRunitAdapter implements RepositoryObserver
{
    /**
     * @param array<string, mixed> $context
     */
    public function onStart(string $operation, array $context = []): void
    {
        // Map CommitIt operation to RunIt Activity
        // This method is called before a repository operation begins
        // Implementation would integrate with RunIt's execution engine
        // For now, this provides a concrete implementation of the RepositoryObserver interface
        // that can be extended with RunIt-specific functionality
    }

    /**
     * @param array<string, mixed> $context
     */
    public function onSuccess(string $operation, array $context = []): void
    {
        // Map CommitIt operation success to RunIt Measurement/Snapshot
        // This method is called when a repository operation completes successfully
    }

    /**
     * @param array<string, mixed> $context
     */
    public function onFailure(string $operation, \Throwable $error, array $context = []): void
    {
        // Map CommitIt operation failure to RunIt Occurrence
        // This method is called when a repository operation fails
    }
}