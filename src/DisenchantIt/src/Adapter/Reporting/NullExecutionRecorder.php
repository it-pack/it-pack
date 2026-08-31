<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Adapter\Reporting;

use ItPack\DisenchantIt\Contract\Reporting\ExecutionRecorderInterface;
use ItPack\DisenchantIt\ValueObject\Execution\ExecutionArtifactInterface;

/**
 * Default ExecutionRecorderInterface implementation: a no-op. Core records
 * through the port unconditionally, so a run is byte-for-byte identical whether
 * or not a real recorder is wired in. A future adapter (OpenTelemetry,
 * CloudEvents, RunIt bridge) replaces this without touching Core.
 */
final readonly class NullExecutionRecorder implements ExecutionRecorderInterface
{
    public function record(ExecutionArtifactInterface $artifact): void
    {
    }
}
