<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\CommandRunner;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\CommandRunner\NativeCommandRunner;
use RuntimeException;

/**
 * Process-level coverage for the shell-free command runner: stdin piping,
 * concurrent stdout/stderr drain, exit-code translation, deadline enforcement
 * and stderr truncation (REVIEW NV3).
 *
 * @covers \ItPack\ConfigureIt\Adapter\CommandRunner\NativeCommandRunner
 */
final class NativeCommandRunnerTest extends Unit
{
    public function testReturnsStdoutOfSimpleCommand(): void
    {
        $output = new NativeCommandRunner()->run([PHP_BINARY, '-r', 'echo "hello";']);

        verify($output)->equals('hello');
    }

    public function testLargeInputIsPipedCompletely(): void
    {
        $payload = str_repeat('x', 300000);

        $output = new NativeCommandRunner()->run(
            [PHP_BINARY, '-r', '$in = stream_get_contents(STDIN); echo strlen($in);'],
            $payload,
        );

        verify($output)->equals((string) strlen($payload));
    }

    public function testLargeOutputDoesNotDeadlock(): void
    {
        $size = 300000;

        $output = new NativeCommandRunner()->run(
            [PHP_BINARY, '-r', 'echo str_repeat("x", ' . (string) $size . ');'],
        );

        verify(strlen($output))->equals($size);
    }

    public function testEmptyInputClosesStdinSoEofReadersTerminate(): void
    {
        $output = new NativeCommandRunner()->run(
            [PHP_BINARY, '-r', '$in = stream_get_contents(STDIN); echo $in === "" ? "eof-ok" : "data";'],
        );

        verify($output)->equals('eof-ok');
    }

    public function testNonZeroExitTranslatesToExceptionWithExitCode(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('(exit 3)');

        new NativeCommandRunner()->run([PHP_BINARY, '-r', 'fwrite(STDERR, "boom"); exit(3);']);
    }

    public function testOversizedStderrIsTruncatedInTheMessage(): void
    {
        try {
            new NativeCommandRunner()->run([PHP_BINARY, '-r', 'fwrite(STDERR, str_repeat("b", 5000)); exit(1);']);
            self::fail('Expected a RuntimeException for the failing subprocess.');
        } catch (RuntimeException $e) {
            verify(str_contains($e->getMessage(), '(truncated)'))->true();
            verify(strlen($e->getMessage()))->lessThan(2400);
        }
    }

    public function testRunawayProcessIsTerminatedAtTheDeadline(): void
    {
        $runner = new NativeCommandRunner(timeoutSeconds: 1);
        $startedAt = microtime(true);

        try {
            $runner->run([PHP_BINARY, '-r', 'sleep(10); echo "late";']);
            self::fail('Expected a timeout RuntimeException.');
        } catch (RuntimeException $e) {
            verify(str_contains($e->getMessage(), 'timed out'))->true();
        }

        verify(microtime(true) - $startedAt)->lessThan(5.0);
    }
}
