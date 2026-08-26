<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\FsWatcher;
use ItPack\ConfigureIt\Execution\ResolutionReporter;

class FsWatcherTest extends Unit
{
    private string $file;

    private string $dir;

    protected function _before(): void
    {
        $this->file = sys_get_temp_dir() . '/fs-watch-' . uniqid() . '.json';
        file_put_contents($this->file, '{"v":1}');

        $this->dir = sys_get_temp_dir() . '/fs-watch-dir-' . uniqid();
        mkdir($this->dir, 0o777, true);
    }

    protected function _after(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }

        $this->removeTree($this->dir);
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $entries = scandir($dir);

        foreach ($entries === false ? [] : array_diff($entries, ['.', '..']) as $item) {
            $path = $dir . '/' . $item;

            if (is_dir($path)) {
                $this->removeTree($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($dir);
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

    /**
     * A watched *directory* is expanded into an inotify watch per contained
     * file; nested directories are skipped (only files are registered).
     */
    public function testDirectoryTargetWatchesEveryContainedFile(): void
    {
        file_put_contents($this->dir . '/a.json', '{"a":1}');
        file_put_contents($this->dir . '/b.json', '{"b":1}');
        mkdir($this->dir . '/nested');
        file_put_contents($this->dir . '/nested/ignored.json', '{"c":1}');

        $reporter = new ResolutionReporter();
        $watcher = new FsWatcher([$this->dir], $reporter);

        $paths = [];
        $watcher->onChange(static function (array $changed) use (&$paths): void {
            $paths = $changed;
        });

        file_put_contents($this->dir . '/b.json', '{"b":2}');

        verify($watcher->await(1000))->true();
        // Listeners receive the configured targets, not the individual file.
        verify($paths)->equals([$this->dir]);

        $payload = $reporter->occurrences()[0]->payload();
        verify($payload['watcher'])->equals('fs');
        verify($payload['paths'])->equals([$this->dir]);

        $watcher->stop();
    }

    public function testUnknownPathsAreIgnoredInsteadOfFailing(): void
    {
        $reporter = new ResolutionReporter();
        $watcher = new FsWatcher([$this->dir . '/does-not-exist.json'], $reporter);

        verify($watcher->await(100))->false();
        verify($reporter->occurrences())->equals([]);

        $watcher->stop();
    }

    public function testEveryListenerIsNotifiedOnASingleEvent(): void
    {
        $reporter = new ResolutionReporter();
        $watcher = new FsWatcher([$this->file], $reporter);

        $seen = [];
        $watcher->onChange(static function (array $paths) use (&$seen): void {
            $seen['first'] = $paths;
        });
        $watcher->onChange(static function (array $paths) use (&$seen): void {
            $seen['second'] = $paths;
        });

        file_put_contents($this->file, '{"v":3}');

        verify($watcher->await(1000))->true();
        verify($seen['first'])->equals([$this->file]);
        verify($seen['second'])->equals([$this->file]);

        $watcher->stop();
    }

    /**
     * The blocking loop keeps performing bounded waits until stop() flips the
     * flag. SIGALRM plays the role of the external "stop signal" so the loop is
     * exercised (and terminated) deterministically instead of relying on event
     * timing.
     */
    public function testBlockingWatchLoopExitsWhenStoppedExternally(): void
    {
        if (!\function_exists('pcntl_alarm') || !\function_exists('pcntl_async_signals')) {
            self::markTestSkipped('pcntl is required to interrupt the blocking watch loop.');
        }

        $reporter = new ResolutionReporter();
        $watcher = new FsWatcher([$this->file], $reporter);

        $previous = pcntl_signal_get_handler(SIGALRM);
        pcntl_async_signals(true);
        pcntl_signal(SIGALRM, static function () use ($watcher): void {
            $watcher->stop();
        });
        pcntl_alarm(1);

        try {
            $watcher->watch();
        } finally {
            pcntl_alarm(0);
            pcntl_signal(SIGALRM, $previous);
        }

        // Loop terminated, resources released, and no phantom change reported.
        verify($watcher->await(50))->false();
        verify($reporter->occurrences())->equals([]);
    }

    public function testWatchReturnsImmediatelyWhenAlreadyStopped(): void
    {
        $reporter = new ResolutionReporter();
        $watcher = new FsWatcher([$this->file], $reporter);

        $watcher->stop();
        $watcher->watch();

        verify($reporter->occurrences())->equals([]);
    }
}
