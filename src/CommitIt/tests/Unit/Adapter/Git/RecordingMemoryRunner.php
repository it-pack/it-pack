<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Git;

use CzProject\GitPhp\CommandProcessor;
use CzProject\GitPhp\Runners\MemoryRunner;

/**
 * {@see MemoryRunner} that records every `run()` invocation so adapter unit
 * tests can assert the expected git command was actually dispatched (kills
 * `MethodCallRemoval` mutants that would otherwise leave the backend call
 * untested).
 *
 * @internal test helper
 */
final class RecordingMemoryRunner extends MemoryRunner
{
    /** @var list<array{args: array<mixed>, env: array<string, scalar>|null, cmd: string}> */
    private array $calls = [];

    private CommandProcessor $processor;

    public function __construct($cwd)
    {
        parent::__construct($cwd);
        $this->processor = new CommandProcessor();
    }

    public function run($cwd, array $args, ?array $env = null)
    {
        $this->calls[] = [
            'args' => $args,
            'env' => $env,
            'cmd' => $this->processor->process('git', $args, $env),
        ];

        return parent::run($cwd, $args, $env);
    }

    /**
     * Assert a command whose processed form (with this call's own env) equals
     * the expected command was dispatched.
     *
     * @param array<int, mixed> $expected
     */
    public function ran(array $expected): bool
    {
        foreach ($this->calls as $call) {
            if ($call['cmd'] === $this->processor->process('git', $expected, $call['env'])) {
                return true;
            }
        }

        return false;
    }
}
