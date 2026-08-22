<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Psr\Http;

use ItPack\RunIt\Adapter\Psr\Contract\ResponseCorrelationEnricherInterface;
use ItPack\RunIt\Contract\Shared\CorrelationKeys;
use Psr\Http\Message\ResponseInterface;

/**
 * Adds execution and correlation identifiers to a response as headers.
 *
 * Header names are configurable. Enrichment never mutates the original response;
 * PSR-7 messages are immutable, so a new instance is returned.
 *
 * @api
 */
final readonly class Psr7ResponseCorrelationEnricher implements ResponseCorrelationEnricherInterface
{
    public function __construct(
        private string $executionHeader = CorrelationKeys::HTTP_EXECUTION_HEADER,
        private string $correlationHeader = CorrelationKeys::HTTP_CORRELATION_HEADER,
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
