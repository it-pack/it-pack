<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Psr\Http\Propagation;

/**
 * Produces the correlation headers injected into outbound HTTP requests.
 *
 * @api
 */
interface HeaderPropagatorInterface
{
    /**
     * @return array<string, string> header name => header value
     */
    public function headers(string $executionId, ?string $correlationId = null): array;
}

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
final class DefaultHeaderPropagator implements HeaderPropagatorInterface
{
    public function __construct(
        private readonly string $executionHeader = 'X-Execution-Id',
        private readonly string $correlationHeader = 'X-Correlation-Id',
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
