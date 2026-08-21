<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\CommandRunner;

use ItPack\ConfigureIt\Contract\CommandRunnerInterface;

/**
 * Runs a command via `proc_open`, piping `input` to stdin and capturing stdout.
 *
 * Command elements are escaped individually (no shell is invoked), so callers
 * must pass an already-split argument list. Non-zero exit throws (M4 — Epic
 * E5.1).
 */
final class NativeCommandRunner implements CommandRunnerInterface
{
    public function run(array $command, string $input = ''): string
    {
        $spec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = proc_open(
            implode(' ', array_map('escapeshellarg', $command)),
            $spec,
            $pipes,
        );

        if (!is_resource($proc)) {
            throw new \RuntimeException('Unable to start command: ' . implode(' ', $command));
        }

        fwrite($pipes[0], $input);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $code = proc_close($proc);

        if ($code !== 0) {
            throw new \RuntimeException(sprintf(
                'Command "%s" failed (exit %d): %s',
                implode(' ', $command),
                $code,
                (string) $stderr,
            ));
        }

        return $stdout === false ? '' : $stdout;
    }
}
