<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\OpenTelemetry;

use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;

/**
 * Contract for the OpenTelemetry bridge publisher.
 *
 * @see https://opentelemetry.io/
 */
interface TelemetryPublisherInterface
{
    public function capability(): BridgeCapabilityInterface;

    public function publish(ExecutionArtifactInterface $artifact): void;
}
