<?php

declare(strict_types=1);

namespace Tests\Support;

use ItPack\CommitIt\Contract\ProcessRunner;
use ItPack\CommitIt\Domain\Exception\VcsOperationException;
use ItPack\CommitIt\Domain\Value\ProcessResult;

/**
 * Memory-based {@see ProcessRunner} for adapter unit tests.
 *
 * Pre-program expected command results via {@see setResult()}; the runner
 * matches commands by string key built from the command and arguments.
 *
 * @internal test helper
 */
final class MemoryProcessRunner implements ProcessRunner
{
    /** @var array<string, ProcessResult> */
    private array $results = [];

    /** @var list<string> */
    private array $calls = [];

    /**
     * @param list<string> $command
     */
    public function setResult(array $command, ProcessResult $result): void
    {
        $this->results[$this->key($command)] = $result;
    }

    public function run(string $workingDir, array $command, ?array $env = null): ProcessResult
    {
        $key = $this->key($command);
        $this->calls[] = $key;

        if (array_key_exists($key, $this->results)) {
            return $this->results[$key];
        }

        throw new VcsOperationException(sprintf('No result programmed for command: %s', $key));
    }

    /**
     * @param list<string> $command
     */
    private function key(array $command): string
    {
        return implode(' ', $command);
    }

    /**
     * @param list<string> $expected
     */
    public function ran(array $expected): bool
    {
        $key = $this->key($expected);

        return in_array($key, $this->calls, true);
    }
}
