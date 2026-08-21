<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

use ItPack\ConfigureIt\Execution\ResolutionReporter;

/**
 * Detects external changes to configuration sources and notifies listeners.
 *
 * A Watcher is the live half of M6 (Epic E4.1). It is intentionally a port:
 * the change-detection mechanism (inotify, a portable poll loop, a remote
 * backend watch) is an adapter concern. When a change is observed the watcher
 * records a `ConfigurationChanged` {@see \ItPack\ConfigureIt\Contract\Execution\OccurrenceInterface}
 * through the supplied {@see ResolutionReporter} and invokes every registered
 * listener so that a reloadable facade can re-resolve atomically.
 *
 * @see docs/ADDITIONAL_FEATURES_PLAN.md §4 "Live Configuration"
 */
interface ConfigurationWatcherInterface
{
    /**
     * Begin watching. Implementations may block (an infinite loop) until
     * {@see stop()} is called, or they may be driven externally via a
     * single-pass probe (adapter-specific, e.g. PollingWatcher::tick()).
     */
    public function watch(): void;

    /**
     * Stop watching and release any underlying resources.
     */
    public function stop(): void;

    /**
     * Register a listener invoked with the changed resource paths whenever a
     * change is detected. Listeners should be cheap: the heavy re-resolution
     * belongs to the subscriber (e.g. ReloadableConfiguration).
     *
     * @param callable(array<int, string> $paths): void $listener
     */
    public function onChange(callable $listener): void;
}
