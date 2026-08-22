<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract;

/**
 * Port for an ExecutionRecorderInterface that also exposes the artifacts it
 * collected as a structured report. Extends the base recorder so a consumer can
 * swap NullExecutionRecorder for a reporting recorder without changing Core.
 */
interface ReportRecorderInterface extends ExecutionRecorderInterface
{
    /**
     * @return list<array<string, mixed>> every recorded artifact, normalized.
     */
    public function reports(): array;
}
