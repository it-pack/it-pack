<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Trace;

use ItPack\RunIt\Adapter\OpenTelemetry\Behavior\TraceBehavior;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\ActivitySpanMapperInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\AttributeKeys;
use ItPack\RunIt\Contract\Domain\ActivityInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\ThrowableDescriptionInterface;
use ItPack\RunIt\Domain\Shared\Duration;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanInterface;
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
        if (($span = $this->startSpan($activity)) === null) {
            return;
        }

        $attributes = [
            AttributeKeys::ACTIVITY_ID => $activity->id()->toString(),
            AttributeKeys::ACTIVITY_NAME => $definition->name()->toString(),
            AttributeKeys::ACTIVITY_KIND => $definition->kind()->toString(),
            AttributeKeys::EXECUTION_ID => $activity->executionId()->toString(),
            AttributeKeys::ACTIVITY_STATUS => $activity->status()->label(),
        ];

        $parentActivityId = $definition->parentActivityId();
        if ($parentActivityId !== null) {
            $attributes[AttributeKeys::ACTIVITY_PARENT_ID] = $parentActivityId->toString();
        }

        $span->setAttributes($attributes);

        $status = $this->statusMap->map($activity->status());
        $span->setStatus($status['code'], $status['description']);

        $throwable = $activity->throwableDescription();
        if ($throwable !== null && $activity->status()->label() === 'failed') {
            $this->recordThrowable($span, $throwable);
        }

        $endedAt = $activity->endedAt() ?? $activity->startedAt();
        $span->end($endedAt->epochSeconds() * Duration::NANOS_PER_SECOND);

        $this->store->recordActivity($activity->id()->toString(), $span->getContext());
    }

    private function resolveParentContext(ActivityInterface|ExecutionInterface $activity): ?ContextInterface
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
