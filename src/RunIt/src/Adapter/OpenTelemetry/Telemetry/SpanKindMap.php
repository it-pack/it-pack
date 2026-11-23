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
    public const string KIND_CONSUMER = 'consumer';
    public const string KIND_PRODUCER = 'producer';
    public const string KIND_SERVER = 'server';
    public const string KIND_CLIENT = 'client';
    public const string KIND_INTERNAL = 'internal';

    /**
     * @return int<0,4>
     */
    public function toSpanKind(string $kind): int
    {
        $normalized = strtolower($kind);

        return match (true) {
            str_contains($normalized, self::KIND_CONSUMER) => SpanKind::KIND_CONSUMER,
            str_contains($normalized, self::KIND_PRODUCER) => SpanKind::KIND_PRODUCER,
            str_contains($normalized, self::KIND_SERVER) => SpanKind::KIND_SERVER,
            str_contains($normalized, self::KIND_CLIENT) => SpanKind::KIND_CLIENT,
            default => SpanKind::KIND_INTERNAL,
        };
    }
}
