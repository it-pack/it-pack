<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Generator;

use ItPack\DeriveIt\Contract\Telemetry\DeriveEventSinkInterface;

/**
 * Null-safe pass-through from the derivation pipeline to the optional
 * {@see DeriveEventSinkInterface} (CONSOLIDATION_PLAN.md §5.2).
 *
 * Owns the single null check so instrumented code paths stay linear regardless
 * of how many events they emit; with no sink wired every method is one
 * null-coalescing no-op. Method names mirror the sink so the bridge mapping to
 * RunIt's recorders stays mechanical.
 */
final readonly class DeriveEventEmitter
{
    public function __construct(
        private ?DeriveEventSinkInterface $sink,
    ) {
    }

    /**
     * @param array<string, string|int|float|bool|null> $attributes
     */
    public function activityStarted(string $name, array $attributes = []): void
    {
        $this->sink?->activityStarted($name, $attributes);
    }

    /**
     * @param array<string, string|int|float|bool|null> $attributes
     */
    public function activityFinished(string $name, bool $successful = true, array $attributes = []): void
    {
        $this->sink?->activityFinished($name, $successful, $attributes);
    }

    /**
     * @param array<string, string|int|float|bool|null> $attributes
     */
    public function measured(string $name, int|float $value, string $unit, array $attributes = []): void
    {
        $this->sink?->measured($name, $value, $unit, $attributes);
    }

    /**
     * @param string $severity PSR-3-shaped literal (`debug`|`info`|`warning`|`error`)
     * @param array<string, string|int|float|bool|null> $attributes
     */
    public function observed(string $message, string $severity, array $attributes = []): void
    {
        $this->sink?->observed($message, $severity, $attributes);
    }

    /**
     * @param array<string, string|int|float|bool|null> $attributes
     */
    public function occurred(string $type, array $attributes = []): void
    {
        $this->sink?->occurred($type, $attributes);
    }
}
