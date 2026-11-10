<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Contract;

use ItPack\RunIt\Contract\Domain\ExecutionInterface;

/**
 * Maps a completed RunIt execution to an OpenTelemetry span.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface ExecutionSpanMapperInterface
{
    public function map(ExecutionInterface $execution): void;

    /**
     * Maps a redacted execution's normalized projection to an OpenTelemetry span.
     *
     * @param array<string, mixed> $normalized redacted execution normalization
     */
    public function mapRedactedExecution(array $normalized): void;
}
