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
    public function testPinsCLocaleDuringRunAndRestoresAfter(): void
    {
        $inner = new class () implements IRunner {
            /** @var array<mixed>|null */
            public ?array $capturedEnv = null;
            public ?string $localeDuringRun = null;
            public ?string $langDuringRun = null;

            public function run($cwd, array $args, ?array $env = null): RunnerResult
            {
                $this->capturedEnv = $env;
                $this->localeDuringRun = (string) getenv('LC_ALL');
                $this->langDuringRun = (string) getenv('LANG');

                return new RunnerResult('git', 0, '', '');
            }

            public function getCwd(): string
            {
                return (string) getcwd();
            }
        };

        $runner = new LocalePinningRunner($inner);
        $runner->run('/tmp', ['status'], ['PATH' => '/usr/bin']);

        // The caller-supplied env is passed through unchanged (we do not pollute
        // the command key the way an env-array override would).
        self::assertSame(['PATH' => '/usr/bin'], $inner->capturedEnv);

        // The locale was pinned for the duration of the git call.
        self::assertSame('C', $inner->localeDuringRun);
        self::assertSame('C', $inner->langDuringRun);

        // And restored afterwards so the host process is not leaked into.
        self::assertNotSame('C', getenv('LC_ALL'));
    }

    public function testPinsLocaleWhenNoEnvProvided(): void
    {
        $inner = new class () implements IRunner {
            public ?string $localeDuringRun = null;

            public function run($cwd, array $args, ?array $env = null): RunnerResult
            {
                $this->localeDuringRun = (string) getenv('LC_ALL');

                return new RunnerResult('git', 0, '', '');
            }

            public function getCwd(): string
            {
                return (string) getcwd();
            }
        };

        $runner = new LocalePinningRunner($inner);
        $runner->run('/tmp', ['status']);

        self::assertSame('C', $inner->localeDuringRun);
    }
}
