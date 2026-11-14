<?php

declare(strict_types=1);

namespace Tests\Support\Fixture\Dogfood;

use Tools\Dogfood\Process\ProcessResult;
use Tools\Dogfood\Process\ProcessRunnerInterface;

final readonly class FakeProcessRunner implements ProcessRunnerInterface
{
    /**
     * @param array<string, int> $exitCodes
     */
    public function __construct(private array $exitCodes = [])
    {
    }

    public function run(string $command, string $workingDirectory): ProcessResult
    {
        $exit = $this->exitCodes[$command] ?? 0;

        return new ProcessResult($command, $exit, 'stdout', $exit === 0 ? '' : 'boom', 1234);
    }
}
