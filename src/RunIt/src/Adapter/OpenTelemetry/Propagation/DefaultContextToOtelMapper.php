<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Propagation;

use ItPack\RunIt\Adapter\OpenTelemetry\Contract\ContextToOtelMapperInterface;
use ItPack\RunIt\Contract\Domain\ExecutionContextInterface;
use OpenTelemetry\API\Baggage\Baggage;
use OpenTelemetry\API\Baggage\BaggageInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;

/**
 * Projects a RunIt execution context into OpenTelemetry baggage.
 *
 * Entries are propagated only when their classification allows it. Secret entries
 * are always dropped, and any entry whose classification is not propagatable is
 * dropped, so sensitive values never leave the process through propagation.
 *
 * @see https://w3id.org/itpack/runit#ExecutionContext
 */
final class DefaultContextToOtelMapper implements ContextToOtelMapperInterface
{
    public function toBaggage(ExecutionContextInterface $context): BaggageInterface
    {
        $builder = Baggage::getBuilder();

        foreach ($context->entries() as $entry) {
            if ($entry->classification()->label() === 'secret') {
                continue;
            }

            if (!$entry->classification()->isPropagatable()) {
                continue;
            }

            $builder->set($entry->key()->toString(), $entry->value());
        }

        return $builder->build();
    }

    /**
     * @param array<string, mixed> $carrier
     * @return array<string, mixed>
     */
    public function injectIntoCarrier(
        array $carrier,
        ExecutionContextInterface $context,
        TextMapPropagatorInterface $propagator,
    ): array {
        $baggage = $this->toBaggage($context);
        $otelContext = Context::getCurrent()->withContextValue($baggage);

        $propagator->inject($carrier, null, $otelContext);

        /** @var array<string, mixed> $carrier */
        return $carrier;
    }
}
