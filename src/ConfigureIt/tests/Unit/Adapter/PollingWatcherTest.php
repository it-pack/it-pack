<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\FileModificationProbe;
use ItPack\ConfigureIt\Adapter\PollingWatcher;
use ItPack\ConfigureIt\Execution\ResolutionReporter;

class PollingWatcherTest extends Unit
{
    private string $file;

    protected function _before(): void
    {
        $this->file = sys_get_temp_dir() . '/poll-watch-' . uniqid() . '.json';
        file_put_contents($this->file, '{"v":1}');
    }

    protected function _after(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

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
}
