<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Telemetry;

use ItPack\RunIt\Adapter\OpenTelemetry\Contract\SpanStatusMapInterface;
use ItPack\RunIt\Contract\Domain\ActivityStatusInterface;
use ItPack\RunIt\Contract\Domain\ExecutionStatusInterface;
use ItPack\RunIt\Domain\Execution\ExecutionStatus;
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
final class SpanStatusMap implements SpanStatusMapInterface
{
    /**
     * @return array{code: 'Unset'|'Ok'|'Error', description: string}
     */
    public function map(ExecutionStatusInterface|ActivityStatusInterface $status): array
    {
        return match ($status->label()) {
            'succeeded' => ['code' => StatusCode::STATUS_OK, 'description' => ''],
            'failed' => ['code' => StatusCode::STATUS_ERROR, 'description' => ExecutionStatus::Failed->value],
            'cancelled' => ['code' => StatusCode::STATUS_ERROR, 'description' => ExecutionStatus::Cancelled->value],
            'timed_out' => ['code' => StatusCode::STATUS_ERROR, 'description' => ExecutionStatus::TimedOut->value],
            default => ['code' => StatusCode::STATUS_UNSET, 'description' => ''],
        };
    }
}
