<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\Enterprise\Contract;

/**
 * Minimal HTTP transport port for enterprise remote sources.
 *
 * Real adapters inject an implementation that performs authentication/signing
 * (AWS SigV4, Azure HMAC, GCP OIDC) and TLS. Tests inject a fake. The Core never
 * depends on this — it lives entirely in the adapter layer (ADR-0002).
 */
interface HttpClient
{
    /**
     * @param array<string, string> $headers
     */
    public function request(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
    ): \ItPack\ConfigureIt\Adapter\Enterprise\Http\HttpResponse;
}
