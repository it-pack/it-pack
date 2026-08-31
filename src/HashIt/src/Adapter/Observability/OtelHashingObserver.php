<?php

declare(strict_types=1);

namespace ItPack\HashIt\Adapter\Observability;

use ItPack\HashIt\Contract\HashingObserverInterface;
use ItPack\HashIt\HashObservation;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;

/**
 * OpenTelemetry tracing adapter for the core {@see \ItPack\HashIt\Contract\HashingObserverInterface} port.
 *
 * Each hashing operation becomes an OpenTelemetry span: `started` opens the span,
 * `progress` reports `hashit.bytes_processed`, `completed` closes it with an `Ok`
 * status (plus duration + result URN), and `failed` records the exception with an
 * `Error` status. Spans are correlated through {@see HashObservation::$id}.
 *
 * This adapter depends only on the OTel *API* (never the SDK), so the host application
 * owns the SDK, the tracer provider and the OTLP exporter (`OTEL_*` env). The
 * composition root never instantiates it automatically — a consumer injects it once
 * (it is only usable when `open-telemetry/api` is installed).
 */
final class OtelHashingObserver implements HashingObserverInterface
{
    /** @var array<string, SpanInterface> */
    private array $spans = [];

    private TracerInterface $tracer;

    public function __construct(?TracerInterface $tracer = null)
    {
        $this->tracer = $tracer ?? Globals::tracerProvider()->getTracer('it-pack/hash-it');
    }

    public function observe(HashObservation $observation): void
    {
        switch ($observation->kind) {
            case HashObservation::STARTED:
                $operationName = $observation->operation === null ? 'HASH' : $observation->operation->name;
                $span = $this->tracer
                    ->spanBuilder($operationName)
                    ->setSpanKind(SpanKind::KIND_INTERNAL)
                    ->setAttribute('hashit.algorithm', $observation->algorithm ?? '')
                    ->setAttribute('hashit.bytes_total', $observation->bytesTotal)
                    ->startSpan()
                ;
                $this->spans[$observation->id] = $span;
                break;

            case HashObservation::PROGRESS:
                $span = $this->spans[$observation->id] ?? null;
                $span?->setAttribute('hashit.bytes_processed', $observation->bytesProcessed);
                break;

            case HashObservation::COMPLETED:
                $span = $this->spans[$observation->id] ?? null;
                unset($this->spans[$observation->id]);
                if ($span === null) {
                    break;
                }
                $span->setStatus(StatusCode::STATUS_OK);
                $span->setAttribute('hashit.duration_ms', $observation->durationMs);
                if ($observation->resultUrn !== null) {
                    $span->setAttribute('hashit.result_urn', $observation->resultUrn);
                }
                $span->end();
                break;

            case HashObservation::FAILED:
                $span = $this->spans[$observation->id] ?? null;
                unset($this->spans[$observation->id]);
                if ($span === null) {
                    break;
                }
                if ($observation->error !== null) {
                    $span->recordException($observation->error);
                }
                $span->setStatus(StatusCode::STATUS_ERROR);
                $span->end();
                break;
        }
    }
}
