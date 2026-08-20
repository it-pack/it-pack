<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Trace;

use ItPack\RunIt\Adapter\OpenTelemetry\BridgeConfig;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\ExecutionSpanMapperInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\ExecutionSpanStoreInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\AttributeKeys;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SpanKindMap;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SpanStatusMap;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\ThrowableDescriptionInterface;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;

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
final class DefaultExecutionSpanMapper implements ExecutionSpanMapperInterface
{
    public function __construct(
        private readonly ?TracerProviderInterface $tracerProvider,
        private readonly ExecutionSpanStoreInterface $store,
        private readonly SpanStatusMap $statusMap,
        private readonly SpanKindMap $kindMap,
    ) {
    }

    public function map(ExecutionInterface $execution): void
    {
        if ($this->tracerProvider === null) {
            return;
        }

        $tracer = $this->tracerProvider->getTracer(BridgeConfig::INSTRUMENTATION_SCOPE);
        $definition = $execution->definition();

        $name = $definition->name()->toString();
        \assert($name !== '');

        $builder = $tracer
            ->spanBuilder($name)
            ->setSpanKind($this->kindMap->toSpanKind($definition->kind()->toString()))
            ->setStartTimestamp($execution->startedAt()->epochSeconds() * 1_000_000_000)
        ;

        $parent = $this->resolveParentContext($execution);
        if ($parent !== null) {
            $builder->setParent($parent);
        }

        $span = $builder->startSpan();

        $attributes = [
            AttributeKeys::EXECUTION_ID => $execution->id()->toString(),
            AttributeKeys::EXECUTION_NAME => $definition->name()->toString(),
            AttributeKeys::EXECUTION_KIND => $definition->kind()->toString(),
            AttributeKeys::EXECUTION_STATUS => $execution->status()->label(),
        ];

        $parentId = $definition->parentExecutionId();
        if ($parentId !== null) {
            $attributes[AttributeKeys::EXECUTION_PARENT_ID] = $parentId->toString();
        }

        $span->setAttributes($attributes);

        $status = $this->statusMap->map($execution->status()->label());
        $span->setStatus($status['code'], $status['description']);

        $throwable = $execution->result()?->throwableDescription();
        if ($execution->status()->label() === 'failed' && $throwable !== null) {
            $this->recordThrowable($span, $throwable);
        }

        $endedAt = $execution->endedAt() ?? $execution->startedAt();
        $span->end($endedAt->epochSeconds() * 1_000_000_000);

        $this->store->recordExecution($execution->id()->toString(), $span->getContext());
    }

    private function resolveParentContext(ExecutionInterface $execution): ?ContextInterface
    {
        $parentId = $execution->definition()->parentExecutionId();
        if ($parentId === null) {
            return null;
        }

        $parentSpan = $this->store->executionContext($parentId->toString());

        return $parentSpan === null ? null : Context::getRoot()->withContextValue(Span::wrap($parentSpan));
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
