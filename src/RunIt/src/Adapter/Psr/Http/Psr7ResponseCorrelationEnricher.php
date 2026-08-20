<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Psr\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * Enriches an outgoing PSR-7 response with RunIt correlation headers.
 *
 * @api
 */
interface ResponseCorrelationEnricherInterface
{
    public function enrich(ResponseInterface $response, string $executionId, ?string $correlationId = null): ResponseInterface;
}

/**
 * Adds execution and correlation identifiers to a response as headers.
 *
 * Header names are configurable. Enrichment never mutates the original response;
 * PSR-7 messages are immutable, so a new instance is returned.
 *
 * @api
 */
final class Psr7ResponseCorrelationEnricher implements ResponseCorrelationEnricherInterface
{
    public function __construct(
        private readonly string $executionHeader = 'X-Execution-Id',
        private readonly string $correlationHeader = 'X-Correlation-Id',
    ) {
    }

    public function enrich(ResponseInterface $response, string $executionId, ?string $correlationId = null): ResponseInterface
    {
        $response = $response->withHeader($this->executionHeader, $executionId);

        if ($correlationId !== null) {
            $response = $response->withHeader($this->correlationHeader, $correlationId);
        }

        return $response;
    }
}
