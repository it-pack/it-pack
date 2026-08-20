<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Telemetry;

use OpenTelemetry\API\Trace\StatusCode;

/**
 * Maps a RunIt terminal status to an OpenTelemetry span status.
 *
 * `succeeded` becomes `Ok`; `failed`, `cancelled`, and `timed_out` become
 * `Error` with a human-readable description so failure analysts can distinguish
 * the terminal reason without parsing attributes. Non-terminal statuses are
 * represented as `Unset`.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class SpanStatusMap
{
    /**
     * @return array{code: 'Unset'|'Ok'|'Error', description: string}
     */
    public function map(string $statusLabel): array
    {
        return match ($statusLabel) {
            'succeeded' => ['code' => StatusCode::STATUS_OK, 'description' => ''],
            'failed' => ['code' => StatusCode::STATUS_ERROR, 'description' => 'failed'],
            'cancelled' => ['code' => StatusCode::STATUS_ERROR, 'description' => 'cancelled'],
            'timed_out' => ['code' => StatusCode::STATUS_ERROR, 'description' => 'timed_out'],
            default => ['code' => StatusCode::STATUS_UNSET, 'description' => ''],
        };
    }
}
