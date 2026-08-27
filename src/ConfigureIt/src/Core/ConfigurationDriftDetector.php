<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use function count;

use ItPack\ConfigureIt\Contract\ConfigurationDriftDetectorInterface;
use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Contract\Execution\ResolutionReporterInterface;
use ItPack\ConfigureIt\Execution\Observation;
use ItPack\ConfigureIt\Execution\Occurrence;
use ItPack\ConfigureIt\ValueObject\ConfigurationDiff;
use ItPack\ConfigureIt\ValueObject\OccurrenceType;
use ItPack\ConfigureIt\ValueObject\Severity;

/**
 * Compares the live configuration graph to a pinned baseline and reports drift.
 *
 * The live graph is obtained through an injectable provider so the detector stays
 * decoupled from any source, reader, or resolver. The baseline is pinned once
 * (constructor or {@see self::pin()}); the detector then reuses
 * {@see ConfigurationDiffer} to explain *what* diverged, records an `Observation`
 * (forwarded as a `config.drift` OpenTelemetry event by the reporter spine) and
 * notifies registered listeners. Behaviour is additive and opt-in (Plan §4).
 *
 * Drift is emitted on transition only, so an interval loop does not re-emit the
 * same divergence every tick (see {@see self::check()}).
 */
final class ConfigurationDriftDetector implements ConfigurationDriftDetectorInterface
{
    /** @var callable(): ConfigurationGraphInterface */
    private $liveProvider;

    private ?ConfigurationGraphInterface $baseline = null;

    private ?ResolutionReporterInterface $reporter;

    private ConfigurationDiffer $differ;

    private float $intervalSeconds;

    private bool $stopped = false;

    /** @var list<callable(ConfigurationDiff, ConfigurationGraphInterface, ConfigurationGraphInterface): void> */
    private array $listeners = [];

    private ?string $lastLiveHash = null;

    /**
     * Hash of the live graph for which we last emitted a drift occurrence, or
     * null when the live graph currently matches the baseline.
     */
    private ?string $reportedDriftHash = null;

    /**
     * @param callable(): ConfigurationGraphInterface              $liveProvider supplies the current
     *        live graph.
     * @param ConfigurationGraphInterface|null                    $baseline      pinned baseline; when null the
     *        first check pins the live graph.
     * @param ResolutionReporterInterface|null                   $reporter      optional reporter; drift is recorded
     *        via Observation + Occurrence.
     * @param ConfigurationDiffer                                $differ        injected differ (reuses Roadmap Phase 6
     *        diffing).
     * @param float                                              $intervalSeconds polling interval for
     *        {@see self::watch()}.
     */
    public function __construct(
        callable $liveProvider,
        ConfigurationDiffer $differ,
        ?ConfigurationGraphInterface $baseline = null,
        ?ResolutionReporterInterface $reporter = null,
        float $intervalSeconds = 5.0,
    ) {
        $this->liveProvider = $liveProvider;
        $this->baseline = $baseline;
        $this->reporter = $reporter;
        $this->differ = $differ;
        $this->intervalSeconds = $intervalSeconds;
    }

    public function pin(): void
    {
        $live = ($this->liveProvider)();

        $this->baseline = $live;
        $this->lastLiveHash = $this->hash($live);
        $this->reportedDriftHash = null;
    }

    /**
     * @phpstan-impure
     */
    public function check(): bool
    {
        $live = ($this->liveProvider)();
        $liveHash = $this->hash($live);

        if (null === $this->baseline) {
            $this->baseline = $live;
            $this->lastLiveHash = $liveHash;
            $this->reportedDriftHash = null;

            return false;
        }

        if ($this->lastLiveHash === $liveHash) {
            return $this->reportedDriftHash === $liveHash;
        }

        $diff = $this->differ->diff($this->baseline, $live);

        if ($diff->empty()) {
            if (null !== $this->reportedDriftHash) {
                $this->reporter?->observe(new Observation(
                    Severity::Info,
                    'Configuration drift resolved: the live graph matches the pinned baseline again.',
                    ['at' => microtime(true)],
                ));
            }

            $this->reportedDriftHash = null;
            $this->lastLiveHash = $liveHash;

            return false;
        }

        $summary = $this->differ->summarize($diff);

        $this->reporter?->record(new Occurrence(OccurrenceType::Drift, microtime(true), [
            'added'   => count($diff->added),
            'removed' => count($diff->removed),
            'changed' => count($diff->changed),
            'summary' => $summary,
        ]));

        $this->reporter?->observe(new Observation(Severity::Warning, $summary, [
            'added'   => count($diff->added),
            'removed' => count($diff->removed),
            'changed' => count($diff->changed),
            'at'      => microtime(true),
        ]));

        foreach ($this->listeners as $listener) {
            $listener($diff, $live, $this->baseline);
        }

        $this->reportedDriftHash = $liveHash;
        $this->lastLiveHash = $liveHash;

        return true;
    }

    public function watch(): void
    {
        $this->stopped = false;

        while (true) {
            $this->check();

            if ($this->stopped) {
                break;
            }

            usleep((int) ($this->intervalSeconds * 1_000_000));
        }
    }

    public function stop(): void
    {
        $this->stopped = true;
    }

    public function onDrift(callable $listener): void
    {
        $this->listeners[] = $listener;
    }

    private function hash(ConfigurationGraphInterface $graph): string
    {
        return md5(serialize($graph->toArray()));
    }
}
