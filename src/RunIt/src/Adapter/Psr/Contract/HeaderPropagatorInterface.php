<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Psr\Contract;

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
