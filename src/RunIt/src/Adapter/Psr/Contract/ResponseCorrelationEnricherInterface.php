<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Psr\Contract;

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
