<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Adapter\Memory;

use ItPack\CommandIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\CommandIt\Contract\Port\CommandExecutionStorageInterface;
use ItPack\CommandIt\Contract\ValueObject\CommandExecutionIdInterface;

/**
 * In-memory execution storage for tests, examples, and short-lived retention.
 *
 * @api
 */
final class InMemoryCommandExecutionStorage implements CommandExecutionStorageInterface
{
    /**
     * @var array<string, ExecutionArtifactInterface>
     */
    private array $store = [];

    public function store(CommandExecutionIdInterface $id, ExecutionArtifactInterface $execution): void
    {
        $this->store[$id->toString()] = $execution;
    }

    public function find(CommandExecutionIdInterface $id): ?ExecutionArtifactInterface
    {
        return $this->store[$id->toString()] ?? null;
    }
}
