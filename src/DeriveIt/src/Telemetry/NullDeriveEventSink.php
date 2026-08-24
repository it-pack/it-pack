<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Telemetry;

use ItPack\DeriveIt\Contract\Telemetry\DeriveEventSinkInterface;

/**
 * Default {@see DeriveEventSinkInterface}: discards every event.
 *
 * Keeps the derivation pipeline's behavior and output byte-identical when no
 * telemetry is wired (the default), with zero dispatch cost beyond one null
 * check per emission site. Instance-scoped like every other collaborator in
 * this package — never a global.
 */
final class NullDeriveEventSink implements DeriveEventSinkInterface
{
    public function activityStarted(string $name, array $attributes = []): void
    {
    }

    public function activityFinished(string $name, bool $successful = true, array $attributes = []): void
    {
    }

    public function measured(string $name, int|float $value, string $unit, array $attributes = []): void
    {
    }

    public function observed(string $message, string $severity, array $attributes = []): void
    {
    }

    public function occurred(string $type, array $attributes = []): void
    {
    }
}
