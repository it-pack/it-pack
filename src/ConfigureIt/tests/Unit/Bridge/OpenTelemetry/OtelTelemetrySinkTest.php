<?php

declare(strict_types=1);

namespace Tests\Unit\Bridge\OpenTelemetry;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Bridge\OpenTelemetry\Contract\OpenTelemetryBridgeInterface;
use ItPack\ConfigureIt\Bridge\OpenTelemetry\OtelTelemetrySink;
use ItPack\ConfigureIt\Bridge\OpenTelemetry\SemanticConventions;

/**
 * Verifies the real OpenTelemetry bridge sink forwards every call to the
 * consumer-supplied {@see OpenTelemetryBridgeInterface} (the only place that
 * knows about the OpenTelemetry SDK).
 */
class OtelTelemetrySinkTest extends Unit
{
    public function testDelegatesEveryCallToTheBridge(): void
    {
        $bridge = new class () implements OpenTelemetryBridgeInterface {
            /** @var list<array{name: string, args: array<mixed>}> */
            public array $calls = [];

            public function startTrace(string $name, array $attributes): string
            {
                $this->calls[] = ['name' => 'startTrace', 'args' => [$name, $attributes]];

                return 'otel-trace-1';
            }

            public function endTrace(string $traceId, array $attributes): void
            {
                $this->calls[] = ['name' => 'endTrace', 'args' => [$traceId, $attributes]];
            }

            public function span(string $traceId, string $name, array $attributes, ?float $startedAt, ?float $endedAt): void
            {
                $this->calls[] = ['name' => 'span', 'args' => [$traceId, $name, $attributes, $startedAt, $endedAt]];
            }

            public function event(string $traceId, string $name, array $attributes): void
            {
                $this->calls[] = ['name' => 'event', 'args' => [$traceId, $name, $attributes]];
            }

            public function increment(string $name, array $attributes, int $value): void
            {
                $this->calls[] = ['name' => 'increment', 'args' => [$name, $attributes, $value]];
            }

            public function measure(string $name, float $value, array $attributes): void
            {
                $this->calls[] = ['name' => 'measure', 'args' => [$name, $value, $attributes]];
            }
        };

        $sink = new OtelTelemetrySink($bridge);

        $traceId = $sink->startTrace(SemanticConventions::SPAN_RESOLVE, ['service.name' => 'x']);
        $sink->span($traceId, SemanticConventions::SPAN_LOAD, ['config.source.kind' => 'file'], 1.0, 1.0);
        $sink->event($traceId, SemanticConventions::EVENT_LOADED, []);
        $sink->increment(SemanticConventions::METRIC_OCCURRENCES, ['config.event' => 'ConfigurationLoaded'], 1);
        $sink->measure(SemanticConventions::METRIC_NODES, 4.0, []);
        $sink->endTrace($traceId, []);

        verify($traceId)->equals('otel-trace-1');

        $names = array_map(static fn (array $c): string => $c['name'], $bridge->calls);
        verify($names)->equals([
            'startTrace',
            'span',
            'event',
            'increment',
            'measure',
            'endTrace',
        ]);

        verify($bridge->calls[1]['args'][1])->equals(SemanticConventions::SPAN_LOAD);
        verify($bridge->calls[2]['args'][1])->equals(SemanticConventions::EVENT_LOADED);
        verify($bridge->calls[3]['args'][0])->equals(SemanticConventions::METRIC_OCCURRENCES);
        verify($bridge->calls[4]['args'][1])->equals(4.0);
    }
}
