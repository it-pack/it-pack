<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\FileModificationProbe;
use ItPack\ConfigureIt\Adapter\PollingWatcher;
use ItPack\ConfigureIt\Execution\ResolutionReporter;
use Tests\Support\Extension\VirtualFileSystem;

class PollingWatcherTest extends Unit
{
    use VirtualFileSystem;

    private string $file;

    public function testDoesNotDispatchOnBaselineTick(): void
    {
        $reporter = new ResolutionReporter();
        $watcher = new PollingWatcher([$this->file], $reporter, 10);

        $fired = 0;
        $watcher->onChange(static function () use (&$fired): void {
            $fired++;
        });

        $watcher->tick();

        verify($fired)->equals(0);
        verify(count($reporter->occurrences()))->equals(0);
    }

    public function testDispatchesWhenSignatureChanges(): void
    {
        $reporter = new ResolutionReporter();
        $watcher = new PollingWatcher([$this->file], $reporter, 10);

        $events = [];
        $watcher->onChange(static function (array $paths) use (&$events): void {
            $events[] = $paths;
        });

        $watcher->tick(); // baseline

        file_put_contents($this->file, '{"v":2}');
        $watcher->tick(); // change

        verify($events)->equals([[$this->file]]);

        $types = array_map(static fn ($o) => $o->type(), $reporter->occurrences());
        verify(in_array('ConfigurationChanged', $types, true))->true();

        $payload = $reporter->occurrences()[0]->payload();
        verify($payload['watcher'])->equals('polling');
    }

    public function testDoesNotDispatchWhenStable(): void
    {
        $reporter = new ResolutionReporter();
        $watcher = new PollingWatcher([$this->file], $reporter, 10);

        $fired = 0;
        $watcher->onChange(static function () use (&$fired): void {
            $fired++;
        });

        $watcher->tick();
        $watcher->tick();
        $watcher->tick();

        verify($fired)->equals(0);
    }

    public function testCustomProbeIsUsed(): void
    {
        $reporter = new ResolutionReporter();
        $signature = 'a';
        $probe = static function () use (&$signature): string {
            return $signature;
        };

        $watcher = new PollingWatcher([$this->file], $reporter, 10, $probe);

        $fired = 0;
        $watcher->onChange(static function () use (&$fired): void {
            $fired++;
        });

        $watcher->tick();
        $signature = 'b';
        $watcher->tick();

        verify($fired)->equals(1);
    }

    public function testDefaultProbeTracksFileEdits(): void
    {
        $probe = new FileModificationProbe([$this->file]);
        $before = $probe();

        file_put_contents($this->file, '{"v":9}');
        clearstatcache();

        verify($probe())->notEquals($before);
    }

    /**
     * The blocking loop keeps ticking until a listener (or any other collaborator)
     * calls stop(). A zero-ish interval keeps the test fast while still exercising
     * the usleep floor of max(1, intervalMs).
     */
    public function testWatchLoopTicksUntilStopped(): void
    {
        $reporter = new ResolutionReporter();
        $calls = 0;
        $probe = static function () use (&$calls): string {
            return 0 === $calls++ ? 'baseline' : 'changed';
        };

        $watcher = new PollingWatcher([$this->file], $reporter, 0, $probe);

        $fired = 0;
        $watcher->onChange(static function () use (&$fired, $watcher): void {
            $fired++;
            $watcher->stop();
        });

        $watcher->watch();

        // Tick 1 = baseline, tick 2 = change -> dispatch -> stop -> loop exits.
        verify($fired)->equals(1);
        verify($calls)->equals(2);
        verify(count($reporter->occurrences()))->equals(1);
    }

    public function testWatchReturnsImmediatelyWhenAlreadyStopped(): void
    {
        $reporter = new ResolutionReporter();
        $calls = 0;
        $probe = static function () use (&$calls): string {
            $calls++;

            return 'never-changes';
        };

        $watcher = new PollingWatcher([$this->file], $reporter, 1000, $probe);
        $watcher->stop();

        $watcher->watch();

        verify($calls)->equals(0);
        verify($reporter->occurrences())->equals([]);
    }

    public function testEveryListenerIsNotifiedWithTheWatchedPaths(): void
    {
        $reporter = new ResolutionReporter();
        $calls = 0;
        $probe = static function () use (&$calls): string {
            return 0 === $calls++ ? 'a' : 'b';
        };

        $watcher = new PollingWatcher([$this->file, $this->file . '.bak'], $reporter, 10, $probe);

        $seen = [];
        $watcher->onChange(static function (array $paths) use (&$seen): void {
            $seen['first'] = $paths;
        });
        $watcher->onChange(static function (array $paths) use (&$seen): void {
            $seen['second'] = $paths;
        });

        $watcher->tick();
        $watcher->tick();

        verify($seen['first'])->equals([$this->file, $this->file . '.bak']);
        verify($seen['second'])->equals([$this->file, $this->file . '.bak']);
        verify($reporter->occurrences()[0]->payload()['paths'])->equals([$this->file, $this->file . '.bak']);
    }

    public function testSignatureReturningToAPreviousValueIsStillAChange(): void
    {
        $reporter = new ResolutionReporter();
        $signatures = ['a', 'b', 'a'];
        $index = 0;
        $probe = static function () use (&$index, $signatures): string {
            return $signatures[$index++] ?? 'a';
        };

        $watcher = new PollingWatcher([$this->file], $reporter, 10, $probe);

        $fired = 0;
        $watcher->onChange(static function () use (&$fired): void {
            $fired++;
        });

        $watcher->tick(); // baseline 'a'
        $watcher->tick(); // 'b' -> change
        $watcher->tick(); // back to 'a' -> change again

        verify($fired)->equals(2);
        verify(count($reporter->occurrences()))->equals(2);
    }

    protected function _before(): void
    {
        $this->file = $this->vfsPath('/poll-watch-' . uniqid() . '.json');
        file_put_contents($this->file, '{"v":1}');
    }

    protected function _after(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }
}
