<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationResolverInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSnapshotInterface;
use ItPack\ConfigureIt\Contract\ConfigurationWatcherInterface;
use ItPack\ConfigureIt\Execution\Observation;
use ItPack\ConfigureIt\Execution\Occurrence;
use ItPack\ConfigureIt\Execution\ResolutionReporter;
use ItPack\ConfigureIt\ValueObject\OccurrenceType;
use ItPack\ConfigureIt\ValueObject\Severity;

/**
 * Live configuration facade: holds an immutable snapshot and re-resolves it on
 * external change, atomically swapping the reference.
 *
 * Wraps a {@see ConfigurationBuilder} (the resolution pipeline) and a
 * {@see ConfigurationWatcherInterface} (the change detector). On construction it
 * resolves the initial snapshot and subscribes to the watcher; every change
 * event triggers a fresh resolution whose result replaces the held snapshot via
 * a single property assignment — the swap is atomic at the language level, and
 * any snapshot previously handed out stays perfectly valid (ADR-0003
 * immutability preserved).
 *
 * If a reload fails, the last-good snapshot keeps being served and the failure
 * is recorded as an `Observation` rather than thrown — a bad edit must never
 * take down a running process.
 *
 * @see docs/ADDITIONAL_FEATURES_PLAN.md §4 "ReloadableConfiguration"
 */
final class ReloadableConfiguration
{
    private ConfigurationSnapshotInterface $snapshot;

    private readonly ConfigurationWatcherInterface $watcher;

    private readonly ResolutionReporter $reporter;

    private readonly ConfigurationResolverInterface $resolver;

    public function __construct(
        ConfigurationBuilder $builder,
        ConfigurationWatcherInterface $watcher,
        ?ResolutionReporter $reporter = null,
    ) {
        $this->reporter = $reporter ?? new ResolutionReporter();
        $this->watcher = $watcher;
        $this->resolver = $builder->build($this->reporter);

        // Fail fast on a broken initial configuration; the app cannot start.
        $this->snapshot = $this->resolver->resolve();

        $watcher->onChange(function (): void {
            $this->reload();
        });
    }

    /**
     * The current (live) snapshot. Capture this reference for stable reads
     * across a reload — it will never mutate.
     */
    public function snapshot(): ConfigurationSnapshotInterface
    {
        return $this->snapshot;
    }

    /**
     * @param mixed $default fallback returned when the path is absent. Configuration
     *        values are intrinsically heterogeneous, so the return type is `mixed`.
     */
    public function get(string $path, mixed $default = null): mixed
    {
        return $this->snapshot->get($path, $default);
    }

    public function has(string $path): bool
    {
        return $this->snapshot->has($path);
    }

    public function exists(string $path): bool
    {
        return $this->snapshot->exists($path);
    }

    public function int(string $path, ?int $default = null): int
    {
        return $this->snapshot->int($path, $default);
    }

    public function float(string $path, ?float $default = null): float
    {
        return $this->snapshot->float($path, $default);
    }

    public function bool(string $path, ?bool $default = null): bool
    {
        return $this->snapshot->bool($path, $default);
    }

    public function string(string $path, ?string $default = null): string
    {
        return $this->snapshot->string($path, $default);
    }

    /**
     * @param array<int, mixed>|null $default
     * @return array<int, mixed>
     */
    public function list(string $path, ?array $default = null): array
    {
        return $this->snapshot->list($path, $default);
    }

    /**
     * @param array<int, string>|null $default
     * @return array<int, string>
     */
    public function stringList(string $path, ?array $default = null): array
    {
        return $this->snapshot->stringList($path, $default);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->snapshot->all();
    }

    /**
     * Re-resolve and atomically swap in the new snapshot.
     *
     * On failure the previous snapshot continues to be served and an
     * `Observation` (level error) is recorded; the exception is swallowed on
     * purpose so a transient or invalid edit cannot crash the host.
     */
    public function reload(): void
    {
        try {
            $next = $this->resolver->resolve();
        } catch (\Throwable $failure) {
            $this->reporter->observe(new Observation(
                Severity::Error,
                'Configuration reload failed; serving last-good snapshot.',
                [
                    'error' => $failure->getMessage(),
                    'watcher' => $this->watcher::class,
                ],
            ));

            return;
        }

        $this->snapshot = $next;

        $this->reporter->record(new Occurrence(
            OccurrenceType::Reloaded,
            microtime(true),
            [
                'watcher' => $this->watcher::class,
            ],
        ));
    }

    /**
     * Start watching. Blocks until {@see stop()} is called.
     */
    public function watch(): void
    {
        $this->watcher->watch();
    }

    public function stop(): void
    {
        $this->watcher->stop();
    }
}
