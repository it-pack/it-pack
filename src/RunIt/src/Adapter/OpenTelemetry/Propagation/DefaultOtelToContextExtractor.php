<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Propagation;

use ItPack\RunIt\Adapter\OpenTelemetry\Contract\OtelToContextExtractorInterface;
use ItPack\RunIt\Contract\Domain\ExecutionContextInterface;
use ItPack\RunIt\Domain\Context\ContextEntry;
use ItPack\RunIt\Domain\Context\ContextKey;
use ItPack\RunIt\Domain\Context\ExecutionContext;
use OpenTelemetry\API\Baggage\Baggage;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;

/**
 * Extracts an OpenTelemetry context from a carrier and projects recovered baggage
 * into a RunIt execution context.
 *
 * The remote parent trace state is preserved in the returned {@see ContextInterface}
 * so the bridge can parent new spans to it. Baggage values recovered from the
 * carrier are re-introduced as propagatable RunIt context entries, and the parent
 * trace id is recorded so executions can correlate with upstream work.
 *
 * @see https://w3id.org/itpack/runit#ExecutionContext
 */
final class DefaultOtelToContextExtractor implements OtelToContextExtractorInterface
{
    public function extractContext(array $carrier, TextMapPropagatorInterface $propagator): ContextInterface
    {
        return $propagator->extract($carrier, null, Context::getCurrent());
    }

    public function toRunItContext(ContextInterface $otelContext): ExecutionContextInterface
    {
        $baggage = Baggage::fromContext($otelContext);
        $context = ExecutionContext::empty();

        foreach ($baggage->getAll() as $key => $entry) {
            if (!is_string($key)) {
                continue;
            }

            /** @var \OpenTelemetry\API\Baggage\Entry $entry */
            $value = $entry->getValue();
            if (is_scalar($value) || is_array($value) || $value === null) {
                /** @var array<int|string, int|float|string|bool|null>|int|float|string|bool|null $safeValue */
                $safeValue = $value;
                $context = $context->with(
                    ContextEntry::internal(ContextKey::fromString($key), $safeValue),
                );
            }
        }

        $spanContext = Span::fromContext($otelContext)->getContext();
        if ($spanContext->isValid()) {
            $context = $context->with(
                ContextEntry::internal(
                    ContextKey::fromString('runit.trace.parent_id'),
                    $spanContext->getTraceId(),
                ),
            );
        }

        return $context;
    }
}
