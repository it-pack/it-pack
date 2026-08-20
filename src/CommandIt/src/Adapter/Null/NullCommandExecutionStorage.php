<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Adapter\Null;

use ItPack\CommandIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\CommandIt\Contract\Port\CommandExecutionStorageInterface;
use ItPack\CommandIt\Contract\ValueObject\CommandExecutionIdInterface;

/**
 * Execution storage that discards everything it is given.
 *
 * The safe default: execution records are produced ephemerally and need not be
 * retained unless a consumer asks for them.
 *
 * @api
 */
final class NullCommandExecutionStorage implements CommandExecutionStorageInterface
{
    public function store(CommandExecutionIdInterface $id, ExecutionArtifactInterface $execution): void
    {
    }

    public function find(CommandExecutionIdInterface $id): ?ExecutionArtifactInterface
    {
        return null;
    }
}
