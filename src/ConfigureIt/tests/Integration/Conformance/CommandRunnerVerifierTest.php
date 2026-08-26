<?php

declare(strict_types=1);

namespace Tests\Integration\Conformance;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\CommandRunner\FakeCommandRunner;
use ItPack\ConfigureIt\Adapter\CommandRunner\NativeCommandRunner;
use ItPack\ConfigureIt\Contract\CommandRunnerInterface;

/**
 * Conformance verifier for the {@see CommandRunnerInterface} port.
 *
 * Every runner implementation — the in-memory fake and the real `proc_open`
 * adapter — must honour the same observable contract: feed `input` to stdin,
 * capture stdout, and return it as a string. Mirrors the sibling-project
 * RunIt verifier convention (parameterized over implementations).
 *
 * Binary-dependent runners (NativeCommandRunner) are skipped when `proc_open`
 * is unavailable; the scenario matrix is identical across implementations so a
 * single contract is exercised by every candidate.
 */
final class CommandRunnerVerifierTest extends Unit
{
    /**
     * @return iterable<string, array{string, CommandRunnerInterface}>
     */
    public function runners(): iterable
    {
        $scenarioKey = 'php -r fwrite(STDOUT, stream_get_contents(STDIN));';

        yield 'fake' => [
            'fake',
            new FakeCommandRunner(byCommand: [$scenarioKey => 'secret']),
        ];

        if (function_exists('proc_open')) {
            yield 'native' => ['native', new NativeCommandRunner()];
        }
    }

    /**
     * @dataProvider runners
     */
    public function testProducesStableStdout(string $name, CommandRunnerInterface $runner): void
    {
        $command = ['php', '-r', 'fwrite(STDOUT, stream_get_contents(STDIN));'];

        $first = $runner->run($command, 'secret');
        $second = $runner->run($command, 'secret');

        verify('' !== $first)->true();
        verify($first)->equals($second);
    }

    public function testNativeRunnerEchoesStdin(): void
    {
        if (!function_exists('proc_open')) {
            self::markTestSkipped('proc_open unavailable');
        }

        $command = ['php', '-r', 'fwrite(STDOUT, stream_get_contents(STDIN));'];

        verify(new NativeCommandRunner()->run($command, 'payload'))->equals('payload');
    }
}
