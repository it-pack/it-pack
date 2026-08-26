<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Contract;

/**
 * Heuristically maps a RunIt kind string to an OpenTelemetry span kind.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#executionKind
 */
interface SpanKindMapInterface
{
    /**
     * @return int<0,4>
     */
    public function toSpanKind(string $kind): int;
}
