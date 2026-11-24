<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\OpenTelemetry;

/**
 * Contract for detecting OpenTelemetry signal capabilities.
 *
 * @see https://opentelemetry.io/
 */
interface BridgeCapabilityInterface
{
    public function any(): bool;
}
