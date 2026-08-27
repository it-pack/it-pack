<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Bridge\OpenTelemetry;

use ItPack\ConfigureIt\Contract\TelemetrySinkInterface;

/**
 * In-memory {@see TelemetrySinkInterface} implementation.
 *
 * Records every telemetry call into typed arrays so tests can assert on the
 * produced traces, spans, events and metrics without any OpenTelemetry SDK or
 * network. Also usable as a lightweight no-op recorder in production when no
 * real backend is wired.
 *
 * @internal test/recording sink; not a transport.
 */
final class InMemoryTelemetrySink implements TelemetrySinkInterface
{
    /**
     * @var array<int, array{id: string, name: string, attributes: array<string, mixed>}>
     */
    private array $traces = [];

    /**
     * @var array<int, array{
     *     traceId: string, name: string, attributes: array<string, mixed>,
     *     startedAt: ?float, endedAt: ?float,
     * }>
     */
    private array $spans = [];

    /**
     * @var array<int, array{traceId: string, name: string, attributes: array<string, mixed>}>
     */
    private array $events = [];

    /**
     * @var array<int, array{name: string, attributes: array<string, mixed>, value: int}>
     */
    private array $counters = [];

    /**
     * @var array<int, array{name: string, attributes: array<string, mixed>, value: float}>
     */
    private array $measures = [];

    private int $traceSeq = 0;

    public function startTrace(string $name, array $attributes): string
    {
        $id = sprintf('trace-%d', ++$this->traceSeq);
        $this->traces[] = ['id' => $id, 'name' => $name, 'attributes' => $attributes];

        return $id;
    }

    public function endTrace(string $traceId, array $attributes): void
    {
        $this->traces[] = ['id' => $traceId, 'name' => 'end', 'attributes' => $attributes];
    }

    public function span(
        string $traceId,
        string $name,
        array $attributes,
        ?float $startedAt = null,
        ?float $endedAt = null,
    ): void {
        $this->spans[] = [
            'traceId' => $traceId,
            'name' => $name,
            'attributes' => $attributes,
            'startedAt' => $startedAt,
            'endedAt' => $endedAt,
        ];
    }

    public function event(string $traceId, string $name, array $attributes): void
    {
        $this->events[] = [
            'traceId' => $traceId,
            'name' => $name,
            'attributes' => $attributes,
        ];
    }

    public function increment(string $name, array $attributes, int $value = 1): void
    {
        $this->counters[] = ['name' => $name, 'attributes' => $attributes, 'value' => $value];
    }

    public function measure(string $name, float $value, array $attributes): void
    {
        $this->measures[] = ['name' => $name, 'attributes' => $attributes, 'value' => $value];
    }

    /**
     * @return array<int, array{id: string, name: string, attributes: array<string, mixed>}>
     */
    public function traces(): array
    {
        return $this->traces;
    }

    /**
     * @return array<int, array{
     *     traceId: string, name: string, attributes: array<string, mixed>,
     *     startedAt: ?float, endedAt: ?float,
     * }>
     */
    public function spans(): array
    {
        return $this->spans;
    }

    /**
     * @return array<int, array{traceId: string, name: string, attributes: array<string, mixed>}>
     */
    public function events(): array
    {
        return $this->events;
    }

    /**
     * @return array<int, array{name: string, attributes: array<string, mixed>, value: int}>
     */
    public function counters(): array
    {
        return $this->counters;
    }

    /**
     * @return array<int, array{name: string, attributes: array<string, mixed>, value: float}>
     */
    public function measures(): array
    {
        return $this->measures;
    }
}
