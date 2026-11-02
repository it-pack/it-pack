<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain;

/**
 * Immutable, validated runtime configuration surface for the scheduler.
 *
 * Backs {@see SchedulerConfiguration} so configuration remains mockable and
 * interface-driven.
 *
 * @see https://w3id.org/itpack/asyncit#SchedulerConfiguration
 * @api
 */
interface SchedulerConfigurationInterface
{
    /**
     * Returns a copy with debug mode enabled.
     */
    public function enableDebug(): self;

    /**
     * Atomically returns a new immutable snapshot with the given overrides.
     *
     * @param array<string, mixed> $diffs key/value pairs matching property names
     */
    public function withChanges(array $diffs): self;
}
