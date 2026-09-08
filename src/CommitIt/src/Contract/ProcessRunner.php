<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Contract;

use ItPack\CommitIt\Contract\Exception\VcsOperationException;
use ItPack\CommitIt\Domain\Value\ProcessResult;

/**
 * Generic port for executing external commands. Allows adapters for
 * different VCS systems to share a single, testable process-execution layer.
 *
 * Implementations must not mutate filesystem state beyond the side effects
 * of the command itself.
 *
 * @api
 */
interface ProcessRunner
{
    /**
     * Execute a command in the given working directory.
     *
     * @param string $workingDir Directory to execute in.
     * @param list<string> $command The command and its arguments (no shell interpolation).
     * @param array<string, scalar>|null $env Environment variables to pass.
     * @return ProcessResult The command output and exit code.
     *
     * @throws VcsOperationException
     *         When the command exits with a non-zero status.
     */
    public function run(string $workingDir, array $command, ?array $env = null): ProcessResult;
}
