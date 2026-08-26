<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Telemetry;

use ItPack\RunIt\Adapter\OpenTelemetry\Contract\SpanKindMapInterface;
use OpenTelemetry\API\Trace\SpanKind;

/**
 * Heuristically maps a RunIt kind string to an OpenTelemetry span kind.
 *
 * Kinds are open, validated strings (for example `http.server.request` or
 * `queue.consumer.process`), so the bridge derives the span kind from well-known
 * keywords rather than an exhaustive enum. When no keyword matches, the span
 * defaults to `INTERNAL`.
 *
 * @see https://w3id.org/itpack/runit#executionKind
 */
final class SpanKindMap implements SpanKindMapInterface
{
    /**
     * @param string $kind
     * @return int<0,4>
     */
    public function toSpanKind(string $kind): int
    {
        $normalized = strtolower($kind);

        if (str_contains($normalized, 'consumer')) {
            return SpanKind::KIND_CONSUMER;
        }

        if (str_contains($normalized, 'producer')) {
            return SpanKind::KIND_PRODUCER;
        }

        if (str_contains($normalized, 'server')) {
            return SpanKind::KIND_SERVER;
        }

        if (str_contains($normalized, 'client')) {
            return SpanKind::KIND_CLIENT;
        }

        return SpanKind::KIND_INTERNAL;
    }
}
