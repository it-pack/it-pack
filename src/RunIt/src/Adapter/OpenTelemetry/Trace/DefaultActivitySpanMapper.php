<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Trace;

use ItPack\RunIt\Adapter\OpenTelemetry\BridgeConfig;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\ActivitySpanMapperInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\ExecutionSpanStoreInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\AttributeKeys;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SpanKindMap;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SpanStatusMap;
use ItPack\RunIt\Contract\Domain\ActivityInterface;
use ItPack\RunIt\Contract\Domain\ThrowableDescriptionInterface;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;

/**
 * Maps a RunIt activity to an OpenTelemetry span.
 *
 * An activity span is parented to its execution's span (when mapped by this
 * publisher) or to a parent activity's span, so the resulting trace represents the
 * activity hierarchy. Activity identity, kind, status, and throwable details are
 * preserved. Cancellation and timeout are recorded distinctly as error statuses
 * with descriptive reasons.
 *
 * @see https://w3id.org/itpack/runit#Activity
 */
final class DefaultActivitySpanMapper implements ActivitySpanMapperInterface
{
    public function __construct(
        private readonly ?TracerProviderInterface $tracerProvider,
        private readonly ExecutionSpanStoreInterface $store,
        private readonly SpanStatusMap $statusMap,
        private readonly SpanKindMap $kindMap,
    ) {
    }

    public function map(ActivityInterface $activity): void
    {
        if ($this->tracerProvider === null) {
            return;
        }

        $tracer = $this->tracerProvider->getTracer(BridgeConfig::INSTRUMENTATION_SCOPE);
        $definition = $activity->definition();

        $name = $definition->name()->toString();
        \assert($name !== '');

        $builder = $tracer
            ->spanBuilder($name)
            ->setSpanKind($this->kindMap->toSpanKind($definition->kind()->toString()))
            ->setStartTimestamp($activity->startedAt()->epochSeconds() * 1_000_000_000)
        ;

        $parent = $this->resolveParentContext($activity);
        if ($parent !== null) {
            $builder->setParent($parent);
        }

        $span = $builder->startSpan();

        $attributes = [
            AttributeKeys::ACTIVITY_ID => $activity->id()->toString(),
            AttributeKeys::ACTIVITY_NAME => $definition->name()->toString(),
            AttributeKeys::ACTIVITY_KIND => $definition->kind()->toString(),
            AttributeKeys::EXECUTION_ID => $activity->executionId()->toString(),
            AttributeKeys::ACTIVITY_STATUS => $activity->status()->name(),
        ];

        $parentActivityId = $definition->parentActivityId();
        if ($parentActivityId !== null) {
            $attributes[AttributeKeys::ACTIVITY_PARENT_ID] = $parentActivityId->toString();
        }

        $span->setAttributes($attributes);

        $status = $this->statusMap->map($activity->status()->name());
        $span->setStatus($status['code'], $status['description']);

        $throwable = $activity->throwableDescription();
        if ($throwable !== null && $activity->status()->name() === 'failed') {
            $this->recordThrowable($span, $throwable);
        }

        $endedAt = $activity->endedAt() ?? $activity->startedAt();
        $span->end($endedAt->epochSeconds() * 1_000_000_000);

        $this->store->recordActivity($activity->id()->toString(), $span->getContext());
    }

    private function resolveParentContext(ActivityInterface $activity): ?ContextInterface
    {
        $parentActivityId = $activity->definition()->parentActivityId();
        if ($parentActivityId !== null) {
            $parentSpan = $this->store->activityContext($parentActivityId->toString());
            if ($parentSpan !== null) {
                return Context::getRoot()->withContextValue(Span::wrap($parentSpan));
            }
        }

        $executionSpan = $this->store->executionContext($activity->executionId()->toString());

        return $executionSpan === null ? null : Context::getRoot()->withContextValue(Span::wrap($executionSpan));
    }

    private function recordThrowable(SpanInterface $span, ThrowableDescriptionInterface $throwable): void
    {
        $span->addEvent('exception', [
            'exception.type' => $throwable->class(),
            'exception.message' => $throwable->message(),
            'exception.stacktrace' => $throwable->trace() ?? '',
        ]);
    }
}
