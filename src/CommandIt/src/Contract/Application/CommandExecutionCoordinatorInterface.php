<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Application;

use ItPack\CommandIt\Contract\Command\CommandDefinitionInterface;
use ItPack\CommandIt\Contract\Command\CommandResultInterface;
use ItPack\CommandIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\CommandIt\Contract\ValueObject\CommandExecutionIdInterface;

/**
 * Owns the command lifecycle state machine and occurrence emission.
 *
 * The coordinator is the only component allowed to transition a
 * {@see ExecutionArtifactInterface} through its lifecycle
 * (validate → received → executing → terminal) and to emit the stable lifecycle
 * occurrences `command.received.v1`, `command.handled.v1`, and `command.failed.v1`.
 * It keeps the executor focused on orchestration rather than lifecycle rules.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface CommandExecutionCoordinatorInterface
{
    /**
     * Marks a freshly-defined execution as received and emits `command.received.v1`.
     */
    public function begin(
        CommandDefinitionInterface $definition,
        CommandExecutionIdInterface $id,
        int $startedAt,
    ): ExecutionArtifactInterface;

    /**
     * Completes a successfully-handled execution, emits `command.handled.v1`, and records the duration.
     */
    public function succeed(
        ExecutionArtifactInterface $execution,
        CommandResultInterface $result,
        int $endedAt,
        int $durationMs,
    ): ExecutionArtifactInterface;

    /**
     * Completes a failed execution, emits `command.failed.v1` (preserving the original error), and records the duration.
     */
    public function fail(
        ExecutionArtifactInterface $execution,
        \Throwable $error,
        int $endedAt,
        int $durationMs,
    ): ExecutionArtifactInterface;
}
