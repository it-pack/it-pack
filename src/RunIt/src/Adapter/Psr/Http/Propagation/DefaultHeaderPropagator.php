<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Psr\Http\Propagation;

use ItPack\RunIt\Adapter\Psr\Contract\HeaderPropagatorInterface;
use ItPack\RunIt\Contract\Shared\CorrelationKeys;

/**
 * Default correlation header propagator.
 *
 * Emits the configured execution-id and (when present) correlation-id header
 * names. Header names and the correlation id are attacker-influenceable only
 * through the context value, which is validated when stored, so the propagator
 * never emits untrusted header names.
 *
 * @api
 */
final readonly class DefaultHeaderPropagator implements HeaderPropagatorInterface
{
    public function __construct(
        private string $executionHeader = CorrelationKeys::HTTP_EXECUTION_HEADER,
        private string $correlationHeader = CorrelationKeys::HTTP_CORRELATION_HEADER,
    ) {
    }

    public function headers(string $executionId, ?string $correlationId = null): array
    {
        $headers = [$this->executionHeader => $executionId];

        if ($correlationId !== null) {
            $headers[$this->correlationHeader] = $correlationId;
        }

        return $headers;
    }
}
