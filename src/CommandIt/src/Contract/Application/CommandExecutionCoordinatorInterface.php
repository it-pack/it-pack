<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Application;

use ItPack\CommandIt\Contract\Command\CommandDefinitionInterface;
use ItPack\CommandIt\Contract\Command\CommandResultInterface;
use ItPack\CommandIt\Contract\ValueObject\CommandExecutionIdInterface;
use ItPack\CommandIt\Domain\CommandExecution;

/**
 * Owns the command lifecycle state machine and occurrence emission.
 *
 * The coordinator is the only component allowed to transition a
 * {@see CommandExecution} through its lifecycle
 * (validate → received → executing → terminal) and to emit the stable lifecycle
 * occurrences `command.received.v1`, `command.handled.v1`, and `command.failed.v1`.
 * It keeps the executor focused on orchestration rather than lifecycle rules.
 *
 * The contract names the concrete {@see CommandExecution} rather than the looser
 * {@see \ItPack\CommandIt\Contract\Domain\ExecutionArtifactInterface} marker: the core
 * pipeline always produces this RunIt-shaped record, so the polymorphic-artifact seam
 * would only hide that fact. Polymorphic artifacts remain a possibility for a future
 * post-model leg and can be reintroduced deliberately then (see ADR 0006).
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
    ): CommandExecution;

    /**
     * Completes a successfully-handled execution, emits `command.handled.v1`, and records the duration.
     */
    public function succeed(
        CommandExecution $execution,
        CommandResultInterface $result,
        int $endedAt,
        int $durationMs,
    ): CommandExecution;

    /**
     * Completes a failed execution, emits `command.failed.v1` (preserving the original error), and records the duration.
     */
    public function fail(
        CommandExecution $execution,
        \Throwable $error,
        int $endedAt,
        int $durationMs,
    ): CommandExecution;
}
