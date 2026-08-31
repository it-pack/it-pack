<?php

declare(strict_types=1);

namespace ItPack\HashIt\Contract;

use ItPack\HashIt\HashObservation;

/**
 * Core-owned observability port. Adapters (OpenTelemetry, CloudEvents) implement
 * this interface to observe hashing without the core depending on any SDK.
 *
 * Single-method, ISP-clean: the core only calls {@see self::observe()} and lets
 * the adapter decide how to render each {@see HashObservation}.
 */
interface HashingObserverInterface
{
    public function observe(HashObservation $observation): void;
}
