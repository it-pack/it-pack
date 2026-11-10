<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Behavior;

use DateTimeInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\BridgeConfig;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\ExecutionSpanStoreInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\SpanKindMapInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\SpanStatusMapInterface;
use ItPack\RunIt\Contract\Domain\ActivityInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Domain\Execution\ExecutionStatus;
use ItPack\RunIt\Domain\Shared\Duration;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\ContextInterface;
use ValueError;

trait TraceBehavior
{
    public function __construct(
        private readonly ?TracerProviderInterface     $tracerProvider,
        private readonly ExecutionSpanStoreInterface  $store,
        private readonly SpanStatusMapInterface      $statusMap,
        private readonly SpanKindMapInterface        $kindMap,
    ) {
    }

    private function startSpan(ExecutionInterface|ActivityInterface $artifact): ?SpanInterface
    {
        if ($this->tracerProvider === null) {
            return null;
        }

        $tracer = $this->tracerProvider->getTracer(BridgeConfig::INSTRUMENTATION_SCOPE);
        $definition = $artifact->definition();

        $name = $definition->name()->toString();
        \assert($name !== '');

        $builder = $tracer
            ->spanBuilder($name)
            ->setSpanKind($this->kindMap->toSpanKind($definition->kind()->toString()))
            ->setStartTimestamp($artifact->startedAt()->epochSeconds() * Duration::NANOS_PER_SECOND)
        ;

        $parent = $this->resolveParentContext($artifact);
        if ($parent !== null) {
            $builder->setParent($parent);
        }

        return $builder->startSpan();
    }

    abstract private function resolveParentContext(ActivityInterface|ExecutionInterface $artifact): ?ContextInterface;

    private function setRedactedStatus(SpanInterface $span, string $status): void
    {
        try {
            $statusResult = $this->statusMap->map(ExecutionStatus::from($status));
            $span->setStatus($statusResult['code'], $statusResult['description']);
        } catch (ValueError) {
            // Unknown status string; surface as Error status to match mapper failure semantics.
            $span->setStatus(StatusCode::STATUS_ERROR, 'unknown_status:' . $status);
        }
    }

    private function endSpanAt(SpanInterface $span, mixed $endedAt): void
    {
        if ($endedAt instanceof DateTimeInterface) {
            $span->end((int) $endedAt->format('U') * Duration::NANOS_PER_SECOND);
            return;
        }

        if (is_int($endedAt)) {
            $span->end($endedAt * Duration::NANOS_PER_SECOND);
            return;
        }

        $span->end();
    }
}
