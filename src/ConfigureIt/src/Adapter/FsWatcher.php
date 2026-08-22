<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter;

use ItPack\ConfigureIt\Contract\ConfigurationWatcherInterface;
use ItPack\ConfigureIt\Execution\Occurrence;
use ItPack\ConfigureIt\Execution\ResolutionReporter;
use ItPack\ConfigureIt\ValueObject\OccurrenceType;

/**
 * Kernel-level file watching via the `inotify` extension.
 *
 * More efficient than polling: the kernel wakes the watcher only when a watched
 * path (or, for directories, a file beneath it) is modified, replaced, created
 * or deleted. {@see watch()} runs a blocking loop interrupted by {@see stop()};
 * {@see await()} performs a single bounded wait so the watcher can also be
 * driven externally (e.g. by tests, or from a cooperative event loop).
 *
 * Requires `ext-inotify`; {@see watch()}/{@see await()} throw if it is absent.
 *
 * @see docs/ADDITIONAL_FEATURES_PLAN.md §4 "FsWatcher (inotify/react)"
 */
final class FsWatcher implements ConfigurationWatcherInterface
{
    private const IN_MODIFY = 0x00000002;
    private const IN_ATTRIB = 0x00000004;
    private const IN_CLOSE_WRITE = 0x00000008;
    private const IN_MOVED_TO = 0x00000080;
    private const IN_CREATE = 0x00000100;
    private const IN_DELETE = 0x00000200;
    private const WATCH_MASK = self::IN_MODIFY | self::IN_ATTRIB | self::IN_CLOSE_WRITE | self::IN_MOVED_TO | self::IN_CREATE | self::IN_DELETE;

    /**
     * @var list<callable(array<int, string>): void>
     */
    private array $listeners = [];

    private bool $stopped = false;

    /**
     * @var resource|null inotify stream.
     */
    private $stream = null;

    /**
     * @param array<int, string> $paths files or directories to watch.
     */
    public function __construct(
        private readonly array $paths,
        private readonly ResolutionReporter $reporter,
    ) {
        if (!extension_loaded('inotify')) {
            throw new \RuntimeException('FsWatcher requires the inotify extension.');
        }

        $this->open();
    }

    public function onChange(callable $listener): void
    {
        $this->listeners[] = $listener;
    }

    public function stop(): void
    {
        $this->stopped = true;

        if (is_resource($this->stream)) {
            fclose($this->stream);
        }

        $this->stream = null;
    }

    public function watch(): void
    {
        $this->open();

        while (!$this->stopped) {
            $this->await();
        }
    }

    /**
     * Perform a single bounded wait for filesystem events.
     *
     * Returns true when at least one change was observed and dispatched. When
     * nothing happens before {@see $timeoutSeconds} elapses (or the watcher is
     * stopped) it returns false without dispatching.
     */
    public function await(int $timeoutMs = 500): bool
    {
        if ($this->stream === null) {
            $this->open();
        }

        if ($this->stopped) {
            return false;
        }

        $stream = $this->stream ?? $this->open();

        $read = [$stream];
        $write = null;
        $except = null;

        $seconds = (int) ($timeoutMs / 1000);
        $microseconds = ($timeoutMs % 1000) * 1000;

        $ready = @stream_select($read, $write, $except, $seconds, $microseconds);

        if ($ready === false || $ready < 1) {
            return false;
        }

        $events = inotify_read($stream);

        if ($events === false || $events === []) {
            return false;
        }

        $this->dispatch();

        return true;
    }

    /**
     * @return resource
     */
    private function open()
    {
        $stream = inotify_init();
        stream_set_blocking($stream, false);

        $targets = $this->paths;

        foreach ($targets as $path) {
            if (is_dir($path)) {
                foreach ($this->filesIn($path) as $file) {
                    inotify_add_watch($stream, $file, self::WATCH_MASK);
                }

                continue;
            }

            if (is_file($path)) {
                inotify_add_watch($stream, $path, self::WATCH_MASK);
            }
        }

        $this->stream = $stream;

        return $stream;
    }

    /**
     * @return list<string>
     */
    private function filesIn(string $dir): array
    {
        $files = [];

        foreach (array_diff(scandir($dir), ['.', '..']) as $item) {
            $target = $dir . '/' . $item;

            if (is_file($target)) {
                $files[] = $target;
            }
        }

        return $files;
    }

    private function dispatch(): void
    {
        $this->reporter->record(new Occurrence(
            OccurrenceType::Changed,
            microtime(true),
            [
                'paths' => $this->paths,
                'watcher' => 'fs',
            ],
        ));

        foreach ($this->listeners as $listener) {
            $listener($this->paths);
        }
    }
}
