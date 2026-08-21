<?php

declare(strict_types=1);

namespace Tools\Dogfood\Process;

/**
 * The outcome of running one dev-tool command in a subprocess.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class ProcessResult
{
    /**
     * @param string $command             the command that was executed
     * @param int    $exitCode            process exit code (0 means success)
     * @param string $stdout              captured standard output
     * @param string $stderr              captured standard error
     * @param int    $durationMicroseconds wall time spent in the subprocess
     */
    public function __construct(
        public string $command,
        public int $exitCode,
        public string $stdout,
        public string $stderr,
        public int $durationMicroseconds,
    ) {
    }

    public function succeeded(): bool
    {
        return $this->exitCode === 0;
    }
}
