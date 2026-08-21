<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Bridge\OpenTelemetry;

use ItPack\ConfigureIt\Bridge\OpenTelemetry\Contract\TelemetrySinkInterface;
use ItPack\ConfigureIt\Contract\Execution\ResolutionReporterInterface;
use ItPack\ConfigureIt\Core\RedactingRenderer;
use ItPack\ConfigureIt\Execution\ExecutionContext;
use ItPack\ConfigureIt\Execution\Observation;
use ItPack\ConfigureIt\Execution\Occurrence;

/**
 * Decorates the Execution reporter spine with native OpenTelemetry instrumentation.
 *
 * The Core resolution pipeline already emits {@see Occurrence}s ("what happened")
 * and {@see Observation}s ("what we noticed") through {@see ResolutionReporterInterface}.
 * This decorator forwards those to a {@see TelemetrySinkInterface}, translating them
 * into OpenTelemetry-modeled traces, configuration lifecycle events and metrics —
 * without Core ever depending on the OpenTelemetry SDK.
 *
 * Telemetry produced:
 *  - one trace per resolution run (`config.resolve`),
 *  - a child span per Occurrence (load/merge/validate/resolve/snapshot),
 *  - a CloudEvents-style lifecycle event per Occurrence,
 *  - a `config.observation` event per Observation (warnings, diagnostics),
 *  - metrics: `config.resolution.duration`, `config.occurrences`,
 *    `config.nodes`, `config.overrides`, `config.unresolved`.
 *
 * @see docs/EXECUTION.md  Execution reporter spine
 * @see docs/ROADMAP.md    Phase 9 — Observability
 */
final class OpenTelemetryReporter implements ResolutionReporterInterface
{
    private ?string $traceId = null;

    private ?float $traceStartedAt = null;

    public function __construct(
        private readonly ResolutionReporterInterface $inner,
        private readonly TelemetrySinkInterface $sink,
        private readonly ?ExecutionContext $context = null,
        private readonly string $serviceName = 'configure-it',
        private readonly RedactingRenderer $renderer = new RedactingRenderer(),
    ) {
    }

    public function record(Occurrence $occurrence): void
    {
        $this->inner->record($occurrence);

        $traceId = $this->ensureTrace($occurrence->at());
        $type = $occurrence->type();

        $span = SemanticConventions::occurrenceSpan($type);

        if ($span !== null) {
            $this->sink->span(
                $traceId,
                $span,
                $this->spanAttributes($occurrence),
                $occurrence->at(),
                $occurrence->at(),
            );
        }

        $event = SemanticConventions::occurrenceEvent($type);

        if ($event !== null) {
            $this->sink->event($traceId, $event, $this->spanAttributes($occurrence));
        }

        $this->sink->increment(
            SemanticConventions::METRIC_OCCURRENCES,
            [SemanticConventions::ATTR_EVENT => $type],
            1,
        );

        if ($type === 'ConfigurationResolved') {
            $payload = $occurrence->payload();

            $this->sink->measure(
                SemanticConventions::METRIC_NODES,
                $this->toFloat($payload['nodes'] ?? 0),
                [],
            );
            $this->sink->measure(
                SemanticConventions::METRIC_OVERRIDES,
                $this->toFloat($payload['overrides'] ?? null),
                [],
            );
            $this->sink->measure(
                SemanticConventions::METRIC_UNRESOLVED,
                $this->toFloat($payload['unresolved'] ?? 0),
                [],
            );
        }

        if (SemanticConventions::closesTrace($type)) {
            $duration = $occurrence->at() - ($this->traceStartedAt ?? $occurrence->at());

            $this->sink->measure(
                SemanticConventions::METRIC_RESOLUTION_DURATION,
                $duration,
                [],
            );
            $this->sink->endTrace($traceId, $this->spanAttributes($occurrence));

            $this->traceId = null;
            $this->traceStartedAt = null;
        }
    }

    public function observe(Observation $observation): void
    {
        $this->inner->observe($observation);

        $traceId = $this->ensureTrace(microtime(true));

        $attributes = [
            SemanticConventions::ATTR_LEVEL => $observation->level(),
            'config.observation.message' => $observation->message(),
        ];

        foreach ($observation->context() as $key => $value) {
            if (\is_scalar($value) || $value === null) {
                $attributes['config.observation.' . $key] = $this->renderer->render($value, false);
            }
        }

        $this->sink->event($traceId, SemanticConventions::EVENT_OBSERVATION, $attributes);
    }

    /**
     * @return list<Occurrence>
     */
    public function occurrences(): array
    {
        return $this->inner->occurrences();
    }

    /**
     * @return list<Observation>
     */
    public function observations(): array
    {
        return $this->inner->observations();
    }

    private function toFloat(mixed $value): float
    {
        return \is_numeric($value) ? (float) $value : 0.0;
    }

    private function ensureTrace(float $at): string
    {
        if ($this->traceId !== null) {
            return $this->traceId;
        }

        $attributes = [SemanticConventions::ATTR_SERVICE => $this->serviceName];

        if ($this->context instanceof ExecutionContext) {
            $attributes[SemanticConventions::ATTR_ENVIRONMENT] = $this->context->environment();

            foreach ($this->context->attributes() as $key => $value) {
                $attributes['config.context.' . $key] = $value;
            }
        }

        $this->traceId = $this->sink->startTrace(SemanticConventions::SPAN_RESOLVE, $attributes);
        $this->traceStartedAt = $at;

        return $this->traceId;
    }

    /**
     * @return array<string, mixed>
     */
    private function spanAttributes(Occurrence $occurrence): array
    {
        $attributes = [SemanticConventions::ATTR_EVENT => $occurrence->type()];

        foreach ($occurrence->payload() as $key => $value) {
            if (!\is_scalar($value) && $value !== null) {
                continue;
            }

            $sensitive = $key === 'value';

            $attributes[match ($key) {
                'origin' => SemanticConventions::ATTR_SOURCE_KIND,
                'priority' => SemanticConventions::ATTR_DOCUMENT_PRIORITY,
                'processors' => SemanticConventions::ATTR_STAGE_COUNT,
                'valid' => SemanticConventions::ATTR_VALID,
                default => 'config.payload.' . $key,
            }] = $this->renderer->render($value, $sensitive);
        }

        return $attributes;
    }
}
