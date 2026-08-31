<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract\Reporting;

use ItPack\DisenchantIt\ValueObject\Execution\ExecutionArtifactInterface;

/**
 * Port through which Core records execution artifacts (context, occurrences,
 * observations, measurements, snapshots). Core depends only on this interface,
 * never on a concrete recorder or an external standard (OpenTelemetry, RunIt),
 * so the library runs identically with or without a recorder. The default
 * wiring is the no-op NullExecutionRecorder.
 */
interface ExecutionRecorderInterface
{
    public function record(ExecutionArtifactInterface $artifact): void;
}
