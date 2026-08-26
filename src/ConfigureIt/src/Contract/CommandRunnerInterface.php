<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

use RuntimeException;

/**
 * Runs an external command, feeding it `input` on stdin and returning stdout.
 *
 * Kept as a port so adapters like `age`/`sops` decrypters can be unit-tested
 * with a fake while the real implementation shells out through
 * {@see \ItPack\ConfigureIt\Adapter\CommandRunner\NativeCommandRunner} (M4).
 */
interface CommandRunnerInterface
{
    /**
     * @param list<string> $command absolute command + args (no shell metacharacters).
     * @param string       $input   data to write to the child process stdin.
     *
     * @return string the child process stdout.
     *
     * @throws RuntimeException when the command cannot be started or exits non-zero.
     */
    public function run(array $command, string $input = ''): string;
}
