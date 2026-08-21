<?php

declare(strict_types=1);

namespace Tools\Dogfood\Process;

/**
 * Runs a dev-tool command and returns its {@see ProcessResult}.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface ProcessRunnerInterface
{
    public function run(string $command, string $workingDirectory): ProcessResult;
}
