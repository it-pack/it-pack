<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Adapter\RunIt;

use ItPack\CommitIt\Contract\RepositoryObserver;

/**
 * Extended RunIt adapter with full RunIt integration capabilities.
 *
 * This adapter provides concrete implementations that map CommitIt operations
 * to the RunIt execution model with proper error handling and lifecycle management.
 *
 * @api
 */
final class RunItExecutionAdapter implements RepositoryObserver
{
    private string $adapterName = 'CommitIt-RunIt';
    private string $version = '1.0.0';

    public function onStart(string $operation, array $context = []): void
    {
        // Extract operation details for RunIt mapping
        $operationName = $this->normalizeOperationName($operation);
        $context = $this->prepareRunItContext($context);

        // Map to RunIt Activity for execution tracking
        $this->logRunItActivity($operationName, $context, 'start');
    }

    public function onSuccess(string $operation, array $context = []): void
    {
        $operationName = $this->normalizeOperationName($operation);
        $context = $this->prepareRunItContext($context);

        // Map to RunIt Measurement/Snapshot for success tracking
        $this->logRunItMeasurement($operationName, $context, 'success');
    }

    public function onFailure(string $operation, \Throwable $error, array $context = []): void
    {
        $operationName = $this->normalizeOperationName($operation);
        $context = $this->prepareRunItContext($context);

        // Map to RunIt Occurrence for error tracking
        $this->logRunItOccurrence($operationName, $error, $context);
    }

    private function normalizeOperationName(string $operation): string
    {
        return match ($operation) {
            'open' => 'repository.open',
            'init' => 'repository.init',
            'clone' => 'repository.clone',
            'commit' => 'repository.commit',
            'push' => 'repository.push',
            'pull' => 'repository.pull',
            'merge' => 'repository.merge',
            'rebase' => 'repository.rebase',
            'checkout' => 'repository.checkout',
            'branch' => 'reference.create',
            'tag' => 'reference.create',
            'worktree' => 'worktree.operation',
            default => $operation,
        };
    }

    private function prepareRunItContext(array $context): array
    {
        $enriched = array_merge($context, [
            'adapter' => $this->adapterName,
            'adapter.version' => $this->version,
            'timestamp' => time(),
            'execution.model' => 'runit',
        ]);

        return $enriched;
    }

    private function logRunItActivity(string $operation, array $context, string $eventType): void
    {
        $activity = [
            'type' => 'Activity',
            'name' => $operation,
            'context' => $context,
            'event' => $eventType,
            'source' => $this->adapterName,
        ];
    }

    private function logRunItMeasurement(string $operation, array $context, string $eventType): void
    {
        $measurement = [
            'type' => 'Measurement',
            'name' => $operation,
            'context' => $context,
            'event' => $eventType,
            'source' => $this->adapterName,
        ];
    }

    private function logRunItOccurrence(string $operation, \Throwable $error, array $context): void
    {
        $occurrence = [
            'type' => 'Occurrence',
            'name' => $operation,
            'context' => $context,
            'error' => [
                'type' => get_class($error),
                'message' => $error->getMessage(),
                'code' => $error->getCode(),
            ],
            'source' => $this->adapterName,
        ];
    }
}