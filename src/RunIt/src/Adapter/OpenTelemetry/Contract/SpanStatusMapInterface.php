<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Contract;

use ItPack\RunIt\Contract\Domain\ActivityStatusInterface;
use ItPack\RunIt\Contract\Domain\ExecutionStatusInterface;

/**
 * Maps a RunIt terminal status to an OpenTelemetry span status.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface SpanStatusMapInterface
{
    /**
     * @return array{code: 'Unset'|'Ok'|'Error', description: string}
     */
    public function map(ExecutionStatusInterface|ActivityStatusInterface $status): array;
}
