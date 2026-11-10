<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Trace;

use ItPack\RunIt\Adapter\OpenTelemetry\Behavior\TraceBehavior;
use ItPack\RunIt\Adapter\OpenTelemetry\BridgeConfig;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\ActivitySpanMapperInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\AttributeKeys;
use ItPack\RunIt\Contract\Domain\ActivityIdInterface;
use ItPack\RunIt\Contract\Domain\ActivityInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\ThrowableDescriptionInterface;
use ItPack\RunIt\Domain\Shared\Duration;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanContextInterface;
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
final readonly class DefaultActivitySpanMapper implements ActivitySpanMapperInterface
{
    use TraceBehavior;

    public function map(ActivityInterface $activity): void
    {
        if (!($span = $this->startSpan($activity)) instanceof SpanInterface) {
            return;
        }

        $definition = $activity->definition();

        $attributes = [
            AttributeKeys::ACTIVITY_ID => $activity->id()->toString(),
            AttributeKeys::ACTIVITY_NAME => $definition->name()->toString(),
            AttributeKeys::ACTIVITY_KIND => $definition->kind()->toString(),
            AttributeKeys::EXECUTION_ID => $activity->executionId()->toString(),
            AttributeKeys::ACTIVITY_STATUS => $activity->status()->label(),
        ];

        $parentActivityId = $definition->parentActivityId();
        if ($parentActivityId instanceof ActivityIdInterface) {
            $attributes[AttributeKeys::ACTIVITY_PARENT_ID] = $parentActivityId->toString();
        }

        $span->setAttributes($attributes);

        $status = $this->statusMap->map($activity->status());
        $span->setStatus($status['code'], $status['description']);

        $throwable = $activity->throwableDescription();
        if ($throwable instanceof ThrowableDescriptionInterface && $activity->status()->label() === 'failed') {
            $this->recordThrowable($span, $throwable);
        }

        $endedAt = $activity->endedAt() ?? $activity->startedAt();
        $span->end($endedAt->epochSeconds() * Duration::NANOS_PER_SECOND);

        $this->store->recordActivity($activity->id()->toString(), $span->getContext());
    }

    public function mapRedactedActivity(array $normalized): void
    {
        $tracerProvider = $this->tracerProvider;
        if (!$tracerProvider instanceof TracerProviderInterface) {
            return;
        }

        $tracer = $tracerProvider->getTracer(BridgeConfig::INSTRUMENTATION_SCOPE);
        $nameValue = $normalized['name'] ?? null;
        $name = is_string($nameValue) && $nameValue !== '' ? $nameValue : 'activity';
        $builder = $tracer->spanBuilder($name);
        $span = $builder->startSpan();

        $activityId = $normalized['id'] ?? null;
        $kind = $normalized['kind'] ?? null;
        $executionId = $normalized['execution_id'] ?? null;
        $status = $normalized['status'] ?? null;

        $attributes = [
            AttributeKeys::ACTIVITY_ID => is_string($activityId) ? $activityId : '',
            AttributeKeys::ACTIVITY_NAME => $name,
            AttributeKeys::ACTIVITY_KIND => is_string($kind) ? $kind : '',
            AttributeKeys::EXECUTION_ID => is_string($executionId) ? $executionId : '',
            AttributeKeys::ACTIVITY_STATUS => is_string($status) ? $status : '',
        ];

        $parentActivityId = $normalized['parent_activity_id'] ?? null;
        if (is_string($parentActivityId)) {
            $attributes[AttributeKeys::ACTIVITY_PARENT_ID] = $parentActivityId;
        }

        $span->setAttributes($attributes);

        $normalizedAttributes = $normalized['attributes'] ?? null;
        if (is_array($normalizedAttributes)) {
            $this->setRedactedAttributes($span, $normalizedAttributes);
        }

        $throwable = $normalized['throwable'] ?? null;
        /** @var array<string, mixed>|null $throwable */
        $throwable = is_array($throwable) ? $throwable : null;
        $this->recordRedactedThrowable($span, $throwable);

        if (is_string($activityId)) {
            $this->store->recordActivity($activityId, $span->getContext());
        }

        $span->end();
    }

    /**
     * @param array<array-key, mixed>|null $attributes
     */
    private function setRedactedAttributes(SpanInterface $span, ?array $attributes): void
    {
        if (!is_array($attributes)) {
            return;
        }

        foreach ($attributes as $key => $value) {
            $keyStr = (string) $key;
            if ($keyStr === '') {
                continue;
            }

            if (is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
                $span->setAttribute($keyStr, $value);
            }
        }
    }

    /**
     * @param array<string, mixed>|null $throwable
     */
    private function recordRedactedThrowable(SpanInterface $span, ?array $throwable): void
    {
        if (!is_array($throwable)) {
            return;
        }

        $throwableClass = is_string($throwable['class'] ?? null) ? $throwable['class'] : '';
        $throwableMessage = is_string($throwable['message'] ?? null) ? $throwable['message'] : '';
        $throwableTrace = is_string($throwable['trace'] ?? null) ? $throwable['trace'] : '';

        $span->addEvent('exception', [
            'exception.type' => $throwableClass,
            'exception.message' => $throwableMessage,
            'exception.stacktrace' => $throwableTrace,
        ]);
    }

    private function resolveParentContext(ActivityInterface|ExecutionInterface $activity): ?ContextInterface
    {
        if (!$activity instanceof ActivityInterface) {
            return null;
        }

        $parentActivityId = $activity->definition()->parentActivityId();
        if ($parentActivityId instanceof ActivityIdInterface) {
            $parentSpan = $this->store->activityContext($parentActivityId->toString());
            if ($parentSpan instanceof SpanContextInterface) {
                return Context::getRoot()->withContextValue(Span::wrap($parentSpan));
            }
        }

        $executionSpan = $this->store->executionContext($activity->executionId()->toString());

        return $executionSpan instanceof SpanContextInterface
            ? Context::getRoot()->withContextValue(Span::wrap($executionSpan))
            : null;
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
