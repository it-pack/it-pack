<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\SDK\Support;

use Closure;
use ItPack\AsyncIt\Contract\TelemetryBridgeInterface;

/**
 * Lazily-materialised {@see TelemetryBridgeInterface}.
 *
 * The underlying {@see TelemetryRecorderInterface} is constructed only on the
 * first {@see self::record()} call, via the injected factory. This keeps
 * optional instrumentation (which may pull in heavy SDKs) out of the hot path
 * and out of the cold start until something actually emits an event
 * ("lazy-loaded optional bridges").
 *
 * @see https://w3id.org/itpack/asyncit#RuntimeAdapter
 */
final class LazyTelemetryBridge implements TelemetryBridgeInterface
{
    private ?TelemetryRecorderInterface $recorder = null;

    /**
     * @param Closure(): TelemetryRecorderInterface $factory builds the recorder on first use
     */
    public function __construct(private readonly Closure $factory)
    {
    }

    public function record(string $event, array $context = []): void
    {
        $this->recorder ??= ($this->factory)();
        $this->recorder->record($event, $context);
    }

    public function isInitialized(): bool
    {
        return $this->recorder !== null;
    }

    /**
     * Recorded events so far, deferred-materialising the recorder on first read.
     *
     * @return list<array{event: string, context: array<string, mixed>}>
     */
    public function events(): array
    {
        return $this->recorder?->events() ?? [];
    }
}
