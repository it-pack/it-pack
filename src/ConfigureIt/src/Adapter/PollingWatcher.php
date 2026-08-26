<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter;

use ItPack\ConfigureIt\Contract\ConfigurationWatcherInterface;
use ItPack\ConfigureIt\Execution\Occurrence;
use ItPack\ConfigureIt\Execution\ResolutionReporter;
use ItPack\ConfigureIt\ValueObject\OccurrenceType;

/**
 * Portable change detection: polls a signature on a fixed interval.
 *
 * Each tick recomputes the signature (default {@see FileModificationProbe} over
 * the watched paths) and, when it differs from the previous value, records a
 * `ConfigurationChanged` occurrence and notifies listeners. The first tick only
 * establishes a baseline and never dispatches.
 *
 * No extension or event system is required, making this the default choice for
 * portability. The blocking {@see watch()} loop can be interrupted via
 * {@see stop()}; tests and non-blocking hosts drive it through {@see tick()}.
 *
 * @see docs/ADDITIONAL_FEATURES_PLAN.md §4 "PollingWatcher adapters"
 */
final class PollingWatcher implements ConfigurationWatcherInterface
{
    /**
     * @var list<callable(array<int, string>): void>
     */
    private array $listeners = [];

    private bool $stopped = false;

    private ?string $lastSignature = null;

    /**
     * @var callable(): string
     */
    private $probe;

    /**
     * @param array<int, string>       $paths      files or directories to watch.
     * @param int                      $intervalMs poll period in milliseconds.
     * @param callable(): string|null $probe      signature provider; defaults to FileModificationProbe.
     */
    public function __construct(
        private readonly array $paths,
        private readonly ResolutionReporter $reporter,
        private readonly int $intervalMs = 1000,
        ?callable $probe = null,
    ) {
        $this->probe = $probe ?? new FileModificationProbe($paths);
    }

    public function onChange(callable $listener): void
    {
        $this->listeners[] = $listener;
    }

    public function stop(): void
    {
        $this->stopped = true;
    }

    public function watch(): void
    {
        while (!$this->stopped) {
            $this->tick();
            usleep(max(1, $this->intervalMs) * 1000);
        }
    }

    /**
     * Perform exactly one poll-and-dispatch cycle. Exposed so callers (and
     * tests) can drive the watcher without entering the blocking {@see watch()}.
     */
    public function tick(): void
    {
        $signature = ($this->probe)();

        if (null === $this->lastSignature) {
            $this->lastSignature = $signature;

            return;
        }

        if ($signature === $this->lastSignature) {
            return;
        }

        $this->lastSignature = $signature;
        $this->dispatch();
    }

    private function dispatch(): void
    {
        $this->reporter->record(new Occurrence(
            OccurrenceType::Changed,
            microtime(true),
            [
                'paths' => $this->paths,
                'watcher' => 'polling',
            ],
        ));

        foreach ($this->listeners as $listener) {
            $listener($this->paths);
        }
    }
}
