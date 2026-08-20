<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Port;

use ItPack\CommandIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\CommandIt\Contract\ValueObject\CommandExecutionIdInterface;

/**
 * Port that retains or persists command execution records.
 *
 * A no-op adapter is the safe default: execution records are produced ephemerally
 * and need not be stored unless a consumer (or a future RunIt adapter) asks for them.
 *
 * @api
 */
interface CommandExecutionStorageInterface
{
    public function store(CommandExecutionIdInterface $id, ExecutionArtifactInterface $execution): void;

    public function find(CommandExecutionIdInterface $id): ?ExecutionArtifactInterface;
}
