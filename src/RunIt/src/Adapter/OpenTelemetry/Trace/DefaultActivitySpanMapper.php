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

        $parentContext = $this->resolveParentContextFromNormalized($normalized);

        $builder = $tracer->spanBuilder($name);
        if ($parentContext instanceof ContextInterface) {
            $builder = $builder->setParent($parentContext);
        }

        $kind = $normalized['kind'] ?? null;
        if (is_string($kind)) {
            $builder = $builder->setSpanKind($this->kindMap->toSpanKind($kind));
        }

        $startedAt = $normalized['started_at'] ?? null;
        if (is_int($startedAt)) {
            $builder = $builder->setStartTimestamp($startedAt * Duration::NANOS_PER_SECOND);
        }

        $span = $builder->startSpan();

        $attributes = $this->buildRedactedActivityAttributes($normalized, $name);

        $parentActivityId = $normalized['parent_activity_id'] ?? null;
        if (is_string($parentActivityId)) {
            $attributes[AttributeKeys::ACTIVITY_PARENT_ID] = $parentActivityId;
        }

        $span->setAttributes($attributes);

        if (is_string($normalized['status'] ?? null)) {
            $this->setRedactedStatus($span, $normalized['status']);
        }

        $snapshotAttributes = $normalized['attributes'] ?? null;
        /** @var array<array-key, mixed>|null $snapshotAttributes */
        $this->setRedactedAttributes($span, $snapshotAttributes);

        $throwable = $normalized['throwable'] ?? null;
        /** @var array<string, mixed>|null $throwable */
        $throwable = is_array($throwable) ? $throwable : null;
        $this->recordRedactedThrowable($span, $throwable);

        $endedAt = $normalized['ended_at'] ?? ($normalized['started_at'] ?? null);
        $this->endSpanAt($span, $endedAt);

        $activityId = $normalized['id'] ?? null;
        if (is_string($activityId)) {
            $this->store->recordActivity($activityId, $span->getContext());
        }
    }

    /**
     * @param array<string, mixed> $normalized
     * @return array<non-empty-string, string>
     */
    private function buildRedactedActivityAttributes(array $normalized, string $name): array
    {
        $activityId = $normalized['id'] ?? null;
        $kind = $normalized['kind'] ?? null;
        $executionId = $normalized['execution_id'] ?? null;
        $status = $normalized['status'] ?? null;

        return [
            AttributeKeys::ACTIVITY_ID => is_string($activityId) ? $activityId : '',
            AttributeKeys::ACTIVITY_NAME => $name,
            AttributeKeys::ACTIVITY_KIND => is_string($kind) ? $kind : '',
            AttributeKeys::EXECUTION_ID => is_string($executionId) ? $executionId : '',
            AttributeKeys::ACTIVITY_STATUS => is_string($status) ? $status : '',
        ];
    }

    /**
     * @param array<string, mixed> $normalized
     */
    private function resolveParentContextFromNormalized(array $normalized): ?ContextInterface
    {
        $parentActivityId = $normalized['parent_activity_id'] ?? null;
        if (is_string($parentActivityId)) {
            $parentSpan = $this->store->activityContext($parentActivityId);
            if ($parentSpan instanceof SpanContextInterface) {
                return Context::getRoot()->withContextValue(Span::wrap($parentSpan));
            }
        }

        $executionId = $normalized['execution_id'] ?? null;
        if (is_string($executionId)) {
            $executionSpan = $this->store->executionContext($executionId);
            if ($executionSpan instanceof SpanContextInterface) {
                return Context::getRoot()->withContextValue(Span::wrap($executionSpan));
            }
        }

        return null;
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
