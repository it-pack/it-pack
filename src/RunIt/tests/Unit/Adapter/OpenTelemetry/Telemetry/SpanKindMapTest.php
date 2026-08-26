<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\OpenTelemetry\Telemetry;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SpanKindMap;
use OpenTelemetry\API\Trace\SpanKind;

/**
 * @group adapter
 * @group open-telemetry
 */
final class SpanKindMapTest extends Unit
{
    public function testKindLabelsMapToTheirOpenTelemetrySpanKinds(): void
    {
        $map = new SpanKindMap();

        $expected = [
            'internal' => SpanKind::KIND_INTERNAL,
            'server' => SpanKind::KIND_SERVER,
            'client' => SpanKind::KIND_CLIENT,
            'producer' => SpanKind::KIND_PRODUCER,
            'consumer' => SpanKind::KIND_CONSUMER,
        ];

        foreach ($expected as $label => $kind) {
            self::assertSame($kind, $map->toSpanKind($label), $label);
        }
    }

    public function testCompoundLabelsResolveByTheirDominantKeyword(): void
    {
        $map = new SpanKindMap();

        self::assertSame(SpanKind::KIND_PRODUCER, $map->toSpanKind('message-producer'));
        self::assertSame(SpanKind::KIND_CLIENT, $map->toSpanKind('ClientCall'));
    }

    public function testUnknownLabelsFallBackToInternal(): void
    {
        $map = new SpanKindMap();

        self::assertSame(SpanKind::KIND_INTERNAL, $map->toSpanKind('something-else'));
    }
}
