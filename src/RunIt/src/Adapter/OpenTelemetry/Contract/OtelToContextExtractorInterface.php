<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Contract;

use ItPack\RunIt\Contract\Domain\ExecutionContextInterface;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;

/**
 * Extracts an OpenTelemetry context from a carrier and projects any recovered
 * baggage back into a RunIt execution context.
 *
 * Remote parent trace state is preserved by the underlying propagator; the
 * extracted {@see ContextInterface} is what the bridge uses as the parent of
 * newly created spans. Baggage recovered from the carrier becomes propagatable
 * RunIt context entries.
 *
 * @api
 */
interface OtelToContextExtractorInterface
{
    /**
     * @param array<string, mixed>       $carrier
     */
    public function extractContext(array $carrier, TextMapPropagatorInterface $propagator): ContextInterface;

    public function toRunItContext(ContextInterface $otelContext): ExecutionContextInterface;
}
