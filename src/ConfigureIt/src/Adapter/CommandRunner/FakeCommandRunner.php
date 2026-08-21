<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\CommandRunner;

use ItPack\ConfigureIt\Contract\CommandRunnerInterface;

/**
 * In-memory {@see CommandRunnerInterface} for unit tests — returns a fixed
 * string regardless of the requested command/input (M4 — Epic E5.1).
 */
final class FakeCommandRunner implements CommandRunnerInterface
{
    /**
     * @param array<string, string> $byCommand optional map of "cmd arg1 arg2" => output.
     */
    public function __construct(
        private string $output = '',
        private array  $byCommand = [],
    ) {
    }

    public function run(array $command, string $input = ''): string
    {
        $key = implode(' ', $command);

        return $this->byCommand[$key] ?? $this->output;
    }
}
