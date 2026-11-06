<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Contract;

use ItPack\RunIt\Contract\Domain\ExecutionContextInterface;
use OpenTelemetry\API\Baggage\BaggageInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;

/**
 * Projects a RunIt execution context into OpenTelemetry baggage and injects it
 * into a carrier using a configurable propagator.
 *
 * Secret and non-propagatable context entries are dropped before they reach
 * OpenTelemetry, so propagation never leaks sensitive values.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#ExecutionContext
 */
interface ContextToOtelMapperInterface
{
    public function toBaggage(ExecutionContextInterface $context): BaggageInterface;

    /**
     * @param array<string, mixed>           $carrier
     * @return array<string, mixed> the mutated carrier
     */
    public function injectIntoCarrier(
        array $carrier,
        ExecutionContextInterface $context,
        TextMapPropagatorInterface $propagator,
    ): array;
}
