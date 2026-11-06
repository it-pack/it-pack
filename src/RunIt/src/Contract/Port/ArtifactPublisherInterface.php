<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Port;

use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use Throwable;

/**
 * Port that emits an artifact produced during execution.
 *
 * Publication is an external effect: the runtime asks the port to emit an
 * artifact, and the concrete adapter decides whether that means an in-memory
 * buffer, a log line, an OpenTelemetry export, or nothing at all. Adapters must
 * never change the artifact, and they must not replace the original business
 * result or exception when publication fails.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface ArtifactPublisherInterface
{
    /**
     * Publishes an artifact.
     *
     * Implementations must be safe to call repeatedly and must not retain the
     * artifact, only its normalized projection if needed.
     *
     * @throws Throwable implementations may throw; callers decide the failure policy
     */
    public function publish(ExecutionArtifactInterface $artifact): void;
}
