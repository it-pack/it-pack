<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Behavior;

use ItPack\RunIt\Adapter\OpenTelemetry\BridgeConfig;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\ExecutionSpanStoreInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SpanKindMap;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SpanStatusMap;
use ItPack\RunIt\Contract\Domain\ActivityInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\ContextInterface;

trait TraceBehavior
{
    public function __construct(
        private ?TracerProviderInterface    $tracerProvider,
        private ExecutionSpanStoreInterface $store,
        private SpanStatusMap               $statusMap,
        private SpanKindMap                 $kindMap,
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
            ->setStartTimestamp($artifact->startedAt()->epochSeconds() * 1_000_000_000)
        ;

        $parent = $this->resolveParentContext($artifact);
        if ($parent !== null) {
            $builder->setParent($parent);
        }

        return $builder->startSpan();
    }

    abstract private function resolveParentContext(ActivityInterface|ExecutionInterface $artifact): ?ContextInterface;
}
