<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Behavior;

use ItPack\RunIt\Adapter\OpenTelemetry\BridgeConfig;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\AttributeValues;
use ItPack\RunIt\Contract\Domain\ThrowableDescriptionInterface;
use ItPack\RunIt\Domain\Shared\Duration;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\ContextInterface;

/**
 * Shared span-mapper helpers used by both activity and execution redacted mappers.
 *
 * The using class must provide:
 * - {@see TraceBehavior} constructor properties: $tracerProvider, $kindMap
 * - resolveParentContextFromNormalized(array): ?ContextInterface
 * - getDefaultRedactedName(): string
 */
trait SpanMapperBehavior
{
    /**
     * @param array<string, mixed> $normalized
     */
    private function buildRedactedSpanBuilder(
        TracerProviderInterface $tracerProvider,
        string $name,
        array $normalized,
    ): SpanBuilderInterface {
        \assert($name !== '');
        $tracer = $tracerProvider->getTracer(BridgeConfig::INSTRUMENTATION_SCOPE);
        $builder = $tracer->spanBuilder($name);
        $parentContext = $this->resolveParentContextFromNormalized($normalized);
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

        return $builder;
    }

    /**
     * @param array<string, mixed> $normalized
     */
    private function resolveRedactedEndedAt(array $normalized): mixed
    {
        return $normalized['ended_at'] ?? ($normalized['started_at'] ?? null);
    }

    /**
     * @param array<string, mixed> $normalized
     * @return non-empty-string
     */
    private function resolveRedactedName(array $normalized): string
    {
        $nameValue = $normalized['name'] ?? null;
        $name = is_string($nameValue) && $nameValue !== '' ? $nameValue : $this->getDefaultRedactedName();
        \assert($name !== '');

        return $name;
    }

    /**
     * @param array<string, mixed> &$attributes
     */
    private function appendStacktraceIfNonEmpty(array &$attributes, ?string $trace): void
    {
        if ($trace !== null && $trace !== '') {
            $attributes['exception.stacktrace'] = $trace;
        }
    }

    private function recordThrowable(SpanInterface $span, ThrowableDescriptionInterface $throwable): void
    {
        $span->addEvent('exception', [
            'exception.type' => $throwable->class(),
            'exception.message' => $throwable->message(),
            'exception.stacktrace' => $throwable->trace() ?? '',
        ]);
    }

    /**
     * @param mixed $result
     */
    private function recordRedactedThrowable(SpanInterface $span, mixed $result): void
    {
        $throwable = is_array($result) ? ($result['throwable'] ?? null) : null;
        if (!is_array($throwable)) {
            return;
        }

        $throwableClass = is_string($throwable['class'] ?? null)
            ? $throwable['class']
            : AttributeValues::EMPTY_EXCEPTION_DETAIL;
        $throwableMessage = is_string($throwable['message'] ?? null)
            ? $throwable['message']
            : AttributeValues::EMPTY_EXCEPTION_DETAIL;
        $throwableTrace = is_string($throwable['trace'] ?? null) ? $throwable['trace'] : null;

        $attributes = [
            'exception.type' => $throwableClass,
            'exception.message' => $throwableMessage,
        ];

        $this->appendStacktraceIfNonEmpty($attributes, $throwableTrace);

        $span->addEvent('exception', $attributes);
    }

    abstract private function getDefaultRedactedName(): string;

    abstract private function resolveParentContextFromNormalized(array $normalized): ?ContextInterface;
}
