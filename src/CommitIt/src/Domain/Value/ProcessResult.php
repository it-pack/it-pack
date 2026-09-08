<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Value;

/**
 * Result of a process execution: stdout, stderr, and exit code.
 *
 * @api
 */
final readonly class ProcessResult
{
    /**
     * @param string $stdout
     * @param string $stderr
     * @param int $exitCode
     */
    public function __construct(
        public string $stdout,
        public string $stderr,
        public int $exitCode,
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->exitCode === 0;
    }

    /**
     * Returns a new ProcessResult with stdout lines split for convenience.
     *
     * @return list<string>
     */
    public function stdoutLines(): array
    {
        $lines = explode("\n", $this->stdout);

        return array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));
    }
}
