<?php

declare(strict_types=1);

namespace Tools\Dogfood\Process;

/**
 * Real subprocess runner backed by {@see proc_open}.
 *
 * It captures stdout and stderr separately, measures wall time with the monotonic
 * clock, and never throws on a non-zero exit: a failed tool is a recorded outcome,
 * not a raised exception, so the dogfooding execution can represent it faithfully.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class ShellProcessRunner implements ProcessRunnerInterface
{
    public function run(string $command, string $workingDirectory): ProcessResult
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $start = hrtime(true);

        $process = proc_open(
            $command,
            $descriptors,
            $pipes,
            $workingDirectory,
            null,
            null,
        );

        if ($process === false || !is_array($pipes)) {
            $duration = max(0, (int) ((hrtime(true) - $start) / 1000));

            return new ProcessResult($command, 127, '', 'Unable to start subprocess.', $duration);
        }

        fclose($pipes[0]);

        $stdout = $this->drain($pipes[1]);
        $stderr = $this->drain($pipes[2]);

        $exitCode = proc_close($process);
        $duration = max(0, (int) ((hrtime(true) - $start) / 1000));

        return new ProcessResult($command, is_int($exitCode) ? $exitCode : 1, $stdout, $stderr, $duration);
    }

    /**
     * @param resource $stream
     */
    private function drain($stream): string
    {
        $buffer = '';

        while (!feof($stream)) {
            $chunk = fread($stream, 8192);

            if ($chunk === false || $chunk === '') {
                break;
            }

            $buffer .= $chunk;
        }

        fclose($stream);

        return $buffer;
    }
}
