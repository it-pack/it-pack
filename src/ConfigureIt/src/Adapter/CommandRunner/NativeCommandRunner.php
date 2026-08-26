<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\CommandRunner;

use ItPack\ConfigureIt\Contract\CommandRunnerInterface;

/**
 * Runs a command via `proc_open`, piping `input` to stdin and capturing stdout.
 *
 * The command is passed to `proc_open` as an **array**, so no shell is ever
 * invoked — callers must pass an already-split argument list. stdin is written
 * while stdout/stderr are drained concurrently (`stream_select`), so large
 * payloads cannot deadlock on pipe buffers. A wall-clock deadline
 * ({@see self::DEFAULT_TIMEOUT_SECONDS}, injectable) terminates runaway
 * processes. Non-zero exit throws (M4 — Epic E5.1); stderr embedded in
 * exception messages is truncated so secret-bearing tool output does not leak
 * into logs.
 */
final readonly class NativeCommandRunner implements CommandRunnerInterface
{
    private const int STDIN_PIPE = 0;
    private const int STDOUT_PIPE = 1;
    private const int STDERR_PIPE = 2;
    public const int DEFAULT_TIMEOUT_SECONDS = 30;
    private const int MAX_STDERR_BYTES = 2048;
    private const int CHUNK_BYTES = 65536;

    public function __construct(
        private readonly int $timeoutSeconds = self::DEFAULT_TIMEOUT_SECONDS,
    ) {
    }

    public function run(array $command, string $input = ''): string
    {
        $spec = [
            self::STDIN_PIPE => ['pipe', 'r'],
            self::STDOUT_PIPE => ['pipe', 'w'],
            self::STDERR_PIPE => ['pipe', 'w'],
        ];

        $proc = proc_open($command, $spec, $pipes);

        if (!is_resource($proc)) {
            throw new \RuntimeException('Unable to start command: ' . implode(' ', $command));
        }

        try {
            $result = $this->drive($proc, $pipes, $input);
        } finally {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
        }

        $exitCode = proc_close($proc);

        if ($result['timedOut']) {
            throw new \RuntimeException(sprintf(
                'Command "%s" timed out after %d second(s): %s',
                implode(' ', $command),
                $this->timeoutSeconds,
                self::truncateStderr($result['stderr']),
            ));
        }

        if ($exitCode !== 0) {
            throw new \RuntimeException(sprintf(
                'Command "%s" failed (exit %d): %s',
                implode(' ', $command),
                $exitCode,
                self::truncateStderr($result['stderr']),
            ));
        }

        return $result['stdout'];
    }

    /**
     * Write `$input` to stdin while draining stdout/stderr until EOF or the
     * deadline expires — the concurrent drain is what keeps large payloads from
     * deadlocking on fixed-size pipe buffers.
     *
     * @param resource $proc
     * @param array<int, resource> $pipes
     * @return array{stdout: string, stderr: string, timedOut: bool}
     */
    private function drive($proc, array $pipes, string $input): array
    {
        $deadline = microtime(true) + max(1, $this->timeoutSeconds);
        $pending = $input;
        $stdinOpen = true;
        $stdout = '';
        $stderr = '';

        // An exhausted (or empty) stdin must be closed immediately: children
        // reading to EOF would otherwise wait forever.
        if ($pending === '') {
            fclose($pipes[self::STDIN_PIPE]);
            $stdinOpen = false;
        }

        while (true) {
            $read = [];
            $write = [];

            if (is_resource($pipes[self::STDOUT_PIPE])) {
                $read[] = $pipes[self::STDOUT_PIPE];
            }

            if (is_resource($pipes[self::STDERR_PIPE])) {
                $read[] = $pipes[self::STDERR_PIPE];
            }

            if ($stdinOpen && is_resource($pipes[self::STDIN_PIPE])) {
                $write[] = $pipes[self::STDIN_PIPE];
            }

            if ($read === [] && $write === []) {
                break;
            }

            $remaining = $deadline - microtime(true);

            if ($remaining <= 0) {
                proc_terminate($proc);

                return ['stdout' => $stdout, 'stderr' => $stderr, 'timedOut' => true];
            }

            $except = null;
            $seconds = (int) floor($remaining);
            $microseconds = (int) (($remaining - $seconds) * 1_000_000);
            $selected = stream_select($read, $write, $except, $seconds, $microseconds);

            if ($selected === false || $selected === 0) {
                // Interrupted or tick elapsed — re-evaluate streams and deadline.
                continue;
            }

            foreach ($read as $stream) {
                $chunk = fread($stream, self::CHUNK_BYTES);

                if ($chunk === '' || $chunk === false) {
                    fclose($stream);
                    continue;
                }

                if ($stream === $pipes[self::STDOUT_PIPE]) {
                    $stdout .= $chunk;
                } else {
                    $stderr .= $chunk;
                }
            }

            foreach ($write as $stream) {
                $written = fwrite($stream, $pending);

                if ($written === false || $written <= 0) {
                    // The child closed its end; writing more would never finish.
                    fclose($stream);
                    $stdinOpen = false;
                    continue;
                }

                $pending = substr($pending, $written);

                if ($pending === '') {
                    fclose($stream);
                    $stdinOpen = false;
                }
            }
        }

        return ['stdout' => $stdout, 'stderr' => $stderr, 'timedOut' => false];
    }

    private static function truncateStderr(string $stderr): string
    {
        return strlen($stderr) > self::MAX_STDERR_BYTES
            ? substr($stderr, 0, self::MAX_STDERR_BYTES) . '…(truncated)'
            : $stderr;
    }
}
