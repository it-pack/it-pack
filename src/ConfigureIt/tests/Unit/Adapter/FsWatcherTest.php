<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\FsWatcher;
use ItPack\ConfigureIt\Execution\ResolutionReporter;

class FsWatcherTest extends Unit
{
    private string $file;

    protected function _before(): void
    {
        $this->file = sys_get_temp_dir() . '/fs-watch-' . uniqid() . '.json';
        file_put_contents($this->file, '{"v":1}');
    }

    protected function _after(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testDispatchesOnFileModification(): void
    {
        $reporter = new ResolutionReporter();
        $watcher = new FsWatcher([$this->file], $reporter);

        $paths = [];
        $watcher->onChange(static function (array $changed) use (&$paths): void {
            $paths = $changed;
        });

        file_put_contents($this->file, '{"v":2}');

        $observed = $watcher->await(1000);

        verify($observed)->true();
        verify($paths)->equals([$this->file]);

        $types = array_map(static fn ($o) => $o->type(), $reporter->occurrences());
        verify(in_array('ConfigurationChanged', $types, true))->true();

        $watcher->stop();
    }

    public function testDoesNotDispatchWhenIdle(): void
    {
        $reporter = new ResolutionReporter();
        $watcher = new FsWatcher([$this->file], $reporter);

        $fired = 0;
        $watcher->onChange(static function () use (&$fired): void {
            $fired++;
        });

        $observed = $watcher->await(200);

        verify($observed)->false();
        verify($fired)->equals(0);

        $watcher->stop();
    }

    public function testStopReleasesResources(): void
    {
        $reporter = new ResolutionReporter();
        $watcher = new FsWatcher([$this->file], $reporter);

        $watcher->stop();

        verify($watcher->await(100))->false();
    }
}
