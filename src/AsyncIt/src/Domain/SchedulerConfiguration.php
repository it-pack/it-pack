<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain;

use DomainException;
use ItPack\AsyncIt\Contract\Port\BackpressurePolicy;
use ItPack\AsyncIt\Contract\Port\SchedulingPolicy;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Domain\Shared\Duration;

/**
 * Immutable, validated runtime configuration for the scheduler.
 *
 * All fields are immutable; {@see self::withChanges()} returns a new snapshot.
 * The scheduler swaps configuration atomically at a safe point (between reactor
 * ticks). Default values come from {@see self::defaults()}, producing a
 * conservative, fail-safe baseline.
 *
 * @see https://w3id.org/itpack/asyncit#SchedulerConfiguration
 */
final readonly class SchedulerConfiguration
{
    public const int MAX_CONCURRENCY_DEFAULT = 256;

    public const int MAX_CONCURRENCY_CEILING = 10_000;

    public const float DEFAULT_TELEMETRY_SAMPLING_RATE = 1.0;

    public const float MAX_CANARY_WEIGHT = 1.0;

    /**
     * @param float $telemetrySamplingRate 0.0 (none) to 1.0 (all)
     * @param float|null $canaryWeight 0.0 (none) to 1.0 (all) fraction of tasks routed to canary
     * @param self|null $canaryConfig The canary configuration snapshot (null when not a canary profile)
     */
    private function __construct(
        public int $maxConcurrency,
        public ?DurationInterface $defaultTimeout,
        public SchedulingPolicy $schedulingPolicy,
        public BackpressurePolicy $backpressurePolicy,
        public float $telemetrySamplingRate,
        public DurationInterface $gracefulShutdownDeadline,
        public bool $debug,
        public ?float $canaryWeight = null,
        public ?self $canaryConfig = null,
    ) {
        if ($maxConcurrency < 1 || $maxConcurrency > self::MAX_CONCURRENCY_CEILING) {
            throw new DomainException(
                'maxConcurrency must be between 1 and ' . self::MAX_CONCURRENCY_CEILING . ", got {$maxConcurrency}.",
            );
        }

        if ($telemetrySamplingRate < 0.0 || $telemetrySamplingRate > self::DEFAULT_TELEMETRY_SAMPLING_RATE) {
            throw new DomainException(
                'telemetrySamplingRate must be between 0.0 and 1.0, got ' . $telemetrySamplingRate . '.',
            );
        }

        if ($canaryWeight !== null && ($canaryWeight < 0.0 || $canaryWeight > self::MAX_CANARY_WEIGHT)) {
            throw new DomainException(
                'canaryWeight must be between 0.0 and 1.0, got ' . $canaryWeight . '.',
            );
        }

        if ($gracefulShutdownDeadline->nanoseconds() <= 0) {
            throw new DomainException('gracefulShutdownDeadline must be positive.');
        }
    }

    public static function defaults(): self
    {
        return new self(
            maxConcurrency: self::MAX_CONCURRENCY_DEFAULT,
            defaultTimeout: null,
            schedulingPolicy: SchedulingPolicy::Fifo,
            backpressurePolicy: BackpressurePolicy::Block,
            telemetrySamplingRate: self::DEFAULT_TELEMETRY_SAMPLING_RATE,
            gracefulShutdownDeadline: Duration::ofSeconds(30),
            debug: false,
        );
    }

    /**
     * Returns a canary profile configuration that routes {@see $weight} fraction
     * of tasks to a canary scheduler. Builds from {@see defaults()} so every
     * non-canary field inherits the baseline defaults, then applies only the
     * canary weight. The returned config carries {@see $canaryWeight} and a
     * null {@see $canaryConfig}; the caller pairs it with a baseline config
     * via {@see CanaryScheduler}.
     *
     * @param float $weight 0.0 (none) to 1.0 (all) fraction routed to canary
     */
    public static function forCanary(float $weight): self
    {
        return self::defaults()->withChanges(['canaryWeight' => $weight]);
    }

    /**
     * Atomically returns a new immutable snapshot with the given overrides.
     *
     * @param array<string, mixed> $diffs key/value pairs matching property names
     * @throws DomainException when a diff key is not a known property
     */
    public function withChanges(array $diffs): self
    {
        $knownKeys = [
            'maxConcurrency',
            'defaultTimeout',
            'schedulingPolicy',
            'backpressurePolicy',
            'telemetrySamplingRate',
            'gracefulShutdownDeadline',
            'debug',
            'canaryWeight',
            'canaryConfig',
        ];

        foreach (array_keys($diffs) as $key) {
            if (!in_array($key, $knownKeys, strict: true)) {
                throw new DomainException(
                    'Unknown configuration key "' . $key . '" in withChanges().',
                );
            }
        }

        /** @var int $maxConcurrency */
        $maxConcurrency = $diffs['maxConcurrency'] ?? $this->maxConcurrency;
        /** @var DurationInterface|null $defaultTimeout */
        $defaultTimeout = $diffs['defaultTimeout'] ?? $this->defaultTimeout;
        /** @var SchedulingPolicy $schedulingPolicy */
        $schedulingPolicy = $diffs['schedulingPolicy'] ?? $this->schedulingPolicy;
        /** @var BackpressurePolicy $backpressurePolicy */
        $backpressurePolicy = $diffs['backpressurePolicy'] ?? $this->backpressurePolicy;
        /** @var float $telemetrySamplingRate */
        $telemetrySamplingRate = $diffs['telemetrySamplingRate'] ?? $this->telemetrySamplingRate;
        /** @var DurationInterface $gracefulShutdownDeadline */
        $gracefulShutdownDeadline = $diffs['gracefulShutdownDeadline'] ?? $this->gracefulShutdownDeadline;
        /** @var bool $debug */
        $debug = $diffs['debug'] ?? $this->debug;
        /** @var float|null $canaryWeight */
        $canaryWeight = $diffs['canaryWeight'] ?? $this->canaryWeight;
        /** @var self|null $canaryConfig */
        $canaryConfig = $diffs['canaryConfig'] ?? $this->canaryConfig;

        return new self(
            maxConcurrency: $maxConcurrency,
            defaultTimeout: $defaultTimeout,
            schedulingPolicy: $schedulingPolicy,
            backpressurePolicy: $backpressurePolicy,
            telemetrySamplingRate: $telemetrySamplingRate,
            gracefulShutdownDeadline: $gracefulShutdownDeadline,
            debug: $debug,
            canaryWeight: $canaryWeight,
            canaryConfig: $canaryConfig,
        );
    }

    /**
     * Returns a copy with debug mode enabled (exposes introspection endpoints).
     *
     * Security: debug surface is absent unless explicitly enabled.
     */
    public function enableDebug(): self
    {
        return $this->withChanges(['debug' => true]);
    }
}
