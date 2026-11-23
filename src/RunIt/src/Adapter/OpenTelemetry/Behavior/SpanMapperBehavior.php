<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Behavior;

use ItPack\RunIt\Adapter\OpenTelemetry\BridgeConfig;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\AttributeValues;
use ItPack\RunIt\Contract\Domain\ThrowableDescriptionInterface;
use ItPack\RunIt\Domain\Shared\ArtifactField;
use ItPack\RunIt\Domain\Shared\Duration;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\SemConv\Attributes\ExceptionAttributes;

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

        $kind = $normalized[ArtifactField::KIND] ?? null;
        if (is_string($kind)) {
            $builder = $builder->setSpanKind($this->kindMap->toSpanKind($kind));
        }

        $startedAt = $normalized[ArtifactField::STARTED_AT] ?? null;
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
        return $normalized[ArtifactField::ENDED_AT] ?? ($normalized[ArtifactField::STARTED_AT] ?? null);
    }

    /**
     * @param array<string, mixed> $normalized
     * @return non-empty-string
     */
    private function resolveRedactedName(array $normalized): string
    {
        $nameValue = $normalized[ArtifactField::NAME] ?? null;
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
            $attributes[ExceptionAttributes::EXCEPTION_STACKTRACE] = $trace;
        }
    }

    private function recordThrowable(SpanInterface $span, ThrowableDescriptionInterface $throwable): void
    {
        $span->addEvent('exception', [
            ExceptionAttributes::EXCEPTION_TYPE => $throwable->class(),
            ExceptionAttributes::EXCEPTION_MESSAGE => $throwable->message(),
            ExceptionAttributes::EXCEPTION_STACKTRACE => $throwable->trace() ?? '',
        ]);
    }

    private function recordRedactedThrowable(SpanInterface $span, mixed $result): void
    {
        $throwable = $this->resolveRedactedThrowable($result);
        if (!is_array($throwable)) {
            return;
        }

        /** @var array<string, mixed> $throwable */
        $attributes = [
            ExceptionAttributes::EXCEPTION_TYPE =>
                $throwable[ArtifactField::THROWABLE_CLASS] ?? AttributeValues::EMPTY_EXCEPTION_DETAIL,
            ExceptionAttributes::EXCEPTION_MESSAGE =>
                $throwable[ArtifactField::MESSAGE] ?? AttributeValues::EMPTY_EXCEPTION_DETAIL,
        ];

        $throwableTrace = is_string($throwable[ArtifactField::TRACE] ?? null) ? $throwable[ArtifactField::TRACE] : null;
        $this->appendStacktraceIfNonEmpty($attributes, $throwableTrace);

        $span->addEvent('exception', $attributes);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveRedactedThrowable(mixed $result): ?array
    {
        /** @var array<string, mixed>|null $throwable */
        $throwable = is_array($result) ? ($result[ArtifactField::THROWABLE] ?? null) : null;

        if (!is_array($throwable)) {
            return null;
        }

        return $throwable;
    }

    abstract private function getDefaultRedactedName(): string;

    abstract private function resolveParentContextFromNormalized(array $normalized): ?ContextInterface;
}
