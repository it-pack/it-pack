<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Git;

use Codeception\Test\Unit;
use CzProject\GitPhp\IRunner;
use CzProject\GitPhp\RunnerResult;
use ItPack\CommitIt\Adapter\Git\LocalePinningRunner;

/**
 * @covers \ItPack\CommitIt\Adapter\Git\LocalePinningRunner
 */
final class LocalePinningRunnerTest extends Unit
{
    public function testPinsLocaleThroughEnvArrayAndLeavesGlobalsUntouched(): void
    {
        $inner = new class () implements IRunner {
            /** @var array<string, string>|null */
            public ?array $capturedEnv = null;

            public function run($cwd, array $args, ?array $env = null): RunnerResult
            {
                /** @var array<string, string> $env */
                $this->capturedEnv = $env;

                return new RunnerResult('git', 0, '', '');
            }

            public function getCwd(): string
            {
                return (string) getcwd();
            }
        };

        $runner = new LocalePinningRunner($inner);

        $beforeLcAll = getenv('LC_ALL');

        $runner->run('/tmp', ['status'], ['PATH' => '/usr/bin']);

        self::assertNotNull($inner->capturedEnv);
        // Locale is pinned inside the child env array, never via a global putenv().
        self::assertSame('C', $inner->capturedEnv['LC_ALL']);
        self::assertSame('C', $inner->capturedEnv['LANG']);
        // Caller-supplied env is preserved alongside the locale pin.
        self::assertSame('/usr/bin', $inner->capturedEnv['PATH']);
        // The host process environment is left untouched (no concurrency race).
        self::assertSame($beforeLcAll, getenv('LC_ALL'));
    }

    public function testOverridesCallerProvidedLocale(): void
    {
        $inner = new class () implements IRunner {
            /** @var array<string, string>|null */
            public ?array $capturedEnv = null;

            public function run($cwd, array $args, ?array $env = null): RunnerResult
            {
                /** @var array<string, string> $env */
                $this->capturedEnv = $env;

                return new RunnerResult('git', 0, '', '');
            }

            public function getCwd(): string
            {
                return (string) getcwd();
            }
        };

        $runner = new LocalePinningRunner($inner);
        $runner->run('/tmp', ['status'], ['LC_ALL' => 'de_DE.UTF-8', 'LANG' => 'de_DE.UTF-8']);

        self::assertNotNull($inner->capturedEnv);
        // The locale pin wins even if the caller tried to set a localized value.
        self::assertSame('C', $inner->capturedEnv['LC_ALL']);
        self::assertSame('C', $inner->capturedEnv['LANG']);
    }

    public function testInheritsFullProcessEnvironmentWhenNoEnvProvided(): void
    {
        $inner = new class () implements IRunner {
            /** @var array<string, string>|null */
            public ?array $capturedEnv = null;

            public function run($cwd, array $args, ?array $env = null): RunnerResult
            {
                /** @var array<string, string> $env */
                $this->capturedEnv = $env;

                return new RunnerResult('git', 0, '', '');
            }

            public function getCwd(): string
            {
                return (string) getcwd();
            }
        };

        $runner = new LocalePinningRunner($inner);
        $runner->run('/tmp', ['status']);

        self::assertNotNull($inner->capturedEnv);
        self::assertSame('C', $inner->capturedEnv['LC_ALL']);
        self::assertSame('C', $inner->capturedEnv['LANG']);
        // When the caller passes no env, the child still inherits the full host
        // environment (PATH/HOME/GIT_*) so git resolves config and binaries.
        if (getenv('PATH') !== false) {
            self::assertSame(getenv('PATH'), $inner->capturedEnv['PATH']);
        }
    }
}
