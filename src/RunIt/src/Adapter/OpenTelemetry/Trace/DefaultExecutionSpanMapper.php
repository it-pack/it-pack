<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Trace;

use ItPack\RunIt\Adapter\OpenTelemetry\Behavior\TraceBehavior;
use ItPack\RunIt\Adapter\OpenTelemetry\BridgeConfig;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\ExecutionSpanMapperInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\AttributeKeys;
use ItPack\RunIt\Contract\Domain\ActivityInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\ThrowableDescriptionInterface;
use ItPack\RunIt\Domain\Execution\ExecutionStatus;
use ItPack\RunIt\Domain\Shared\Duration;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;
use ValueError;

/**
 * Maps a RunIt execution to an OpenTelemetry span.
 *
 * A root execution becomes a root span (or a child of the currently active
 * OpenTelemetry context); a child execution becomes a child span of the parent
 * execution's span when that parent was mapped by this publisher. The execution
 * identity, kind, status, and safe failure details are preserved as attributes.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class DefaultExecutionSpanMapper implements ExecutionSpanMapperInterface
{
    use TraceBehavior;

    public function map(ExecutionInterface $execution): void
    {
        if (!($span = $this->startSpan($execution)) instanceof SpanInterface) {
            return;
        }

        $definition = $execution->definition();

        $attributes = [
            AttributeKeys::EXECUTION_ID => $execution->id()->toString(),
            AttributeKeys::EXECUTION_NAME => $definition->name()->toString(),
            AttributeKeys::EXECUTION_KIND => $definition->kind()->toString(),
            AttributeKeys::EXECUTION_STATUS => $execution->status()->label(),
        ];

        $parentId = $definition->parentExecutionId();
        if ($parentId instanceof ExecutionIdInterface) {
            $attributes[AttributeKeys::EXECUTION_PARENT_ID] = $parentId->toString();
        }

        $span->setAttributes($attributes);

        $status = $this->statusMap->map($execution->status());
        $span->setStatus($status['code'], $status['description']);

        $throwable = $execution->result()?->throwableDescription();
        if ($execution->status()->label() === 'failed' && $throwable instanceof ThrowableDescriptionInterface) {
            $this->recordThrowable($span, $throwable);
        }

        $endedAt = $execution->endedAt() ?? $execution->startedAt();
        $span->end($endedAt->epochSeconds() * Duration::NANOS_PER_SECOND);

        $this->store->recordExecution($execution->id()->toString(), $span->getContext());
    }

    public function mapRedactedExecution(array $normalized): void
    {
        $tracerProvider = $this->tracerProvider;
        if (!$tracerProvider instanceof TracerProviderInterface) {
            return;
        }

        $tracer = $tracerProvider->getTracer(BridgeConfig::INSTRUMENTATION_SCOPE);
        $nameValue = $normalized['name'] ?? null;
        $name = is_string($nameValue) && $nameValue !== '' ? $nameValue : 'execution';

        $builder = $tracer->spanBuilder($name);
        $span = $builder->startSpan();

        $executionId = $normalized['id'] ?? null;
        $kind = $normalized['kind'] ?? null;
        $status = $normalized['status'] ?? null;

        $attributes = [
            AttributeKeys::EXECUTION_ID => is_string($executionId) ? $executionId : '',
            AttributeKeys::EXECUTION_NAME => $name,
            AttributeKeys::EXECUTION_KIND => is_string($kind) ? $kind : '',
            AttributeKeys::EXECUTION_STATUS => is_string($status) ? $status : '',
        ];

        $parentId = $normalized['parent_execution_id'] ?? null;
        if (is_string($parentId)) {
            $attributes[AttributeKeys::EXECUTION_PARENT_ID] = $parentId;
        }

        $span->setAttributes($attributes);

        $statusValue = $normalized['status'] ?? null;
        if (is_string($statusValue)) {
            try {
                $statusResult = $this->statusMap->map(ExecutionStatus::from($statusValue));
                $span->setStatus($statusResult['code'], $statusResult['description']);
            } catch (ValueError) {
                // Unknown status string; skip status mapping.
            }
        }

        $result = $normalized['result'] ?? null;
        /** @var array<string, mixed>|null $result */
        $result = is_array($result) ? $result : null;
        $this->recordRedactedThrowable($span, $result);

        if (is_string($executionId)) {
            $this->store->recordExecution($executionId, $span->getContext());
        }

        $span->end();
    }

    /**
     * @param array<string, mixed>|null $result
     */
    private function recordRedactedThrowable(SpanInterface $span, ?array $result): void
    {
        if (!is_array($result) || !isset($result['throwable']) || !is_array($result['throwable'])) {
            return;
        }

        $throwable = $result['throwable'];
        $throwableClass = is_string($throwable['class'] ?? null) ? $throwable['class'] : '';
        $throwableMessage = is_string($throwable['message'] ?? null) ? $throwable['message'] : '';
        $throwableTrace = is_string($throwable['trace'] ?? null) ? $throwable['trace'] : '';

        $span->addEvent('exception', [
            'exception.type' => $throwableClass,
            'exception.message' => $throwableMessage,
            'exception.stacktrace' => $throwableTrace,
        ]);
    }

    private function resolveParentContext(ExecutionInterface|ActivityInterface $execution): ?ContextInterface
    {
        if (!$execution instanceof ExecutionInterface) {
            return null;
        }

        $parentId = $execution->definition()->parentExecutionId();
        if (!$parentId instanceof ExecutionIdInterface) {
            return null;
        }

        $parentSpan = $this->store->executionContext($parentId->toString());

        return $parentSpan instanceof SpanContextInterface
            ? Context::getRoot()->withContextValue(Span::wrap($parentSpan))
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
