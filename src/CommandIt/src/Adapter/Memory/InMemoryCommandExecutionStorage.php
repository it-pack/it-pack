<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Adapter\Memory;

use ItPack\CommandIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\CommandIt\Contract\Port\CommandExecutionStorageInterface;
use ItPack\CommandIt\Contract\ValueObject\CommandExecutionIdInterface;

/**
 * In-memory execution storage for tests, examples, and short-lived retention.
 *
 * The store is bounded: once more than {@see self::$maxEntries} distinct executions
 * are retained, the oldest insertion is evicted (F-17). Pass `0` to keep the store
 * unbounded; that is documented as a deliberate escape hatch, not the default, so
 * production-shaped runtimes cannot retain executions without limit.
 *
 * @api
 */
final class InMemoryCommandExecutionStorage implements CommandExecutionStorageInterface
{
    /**
     * Default bound: retain at most this many executions, evicting the oldest.
     */
    public const int DEFAULT_MAX_ENTRIES = 1000;

    /**
     * @var array<string, ExecutionArtifactInterface>
     */
    private array $store = [];

    public function __construct(
        private readonly int $maxEntries = self::DEFAULT_MAX_ENTRIES,
    ) {
    }

    public function store(CommandExecutionIdInterface $id, ExecutionArtifactInterface $execution): void
    {
        $key = $id->toString();

        if (isset($this->store[$key])) {
            $this->store[$key] = $execution;

            return;
        }

        if ($this->maxEntries > 0 && \count($this->store) >= $this->maxEntries) {
            // The store is non-empty here (count >= maxEntries > 0), so the oldest
            // key always exists; evict it without reindexing the remaining entries.
            unset($this->store[\array_key_first($this->store)]);
        }

        $this->store[$key] = $execution;
    }

    public function find(CommandExecutionIdInterface $id): ?ExecutionArtifactInterface
    {
        return $this->store[$id->toString()] ?? null;
    }

    /**
     * Number of executions currently retained.
     */
    public function count(): int
    {
        return \count($this->store);
    }

    /**
     * Drops every retained execution.
     */
    public function clear(): void
    {
        $this->store = [];
    }
}
