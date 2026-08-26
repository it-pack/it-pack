<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\Enterprise\Http;

use ItPack\ConfigureIt\Adapter\Enterprise\Contract\HttpClient;
use RuntimeException;

/**
 * In-memory {@see HttpClient} for unit tests — returns a queued response for the
 * first URL that contains the configured needle.
 */
final readonly class FakeHttpClient implements HttpClient
{
    /**
     * @param array<string, HttpResponse> $responses map of URL-substring => response.
     */
    public function __construct(private array $responses = [])
    {
    }

    /**
     * @param array<string, string> $headers
     */
    public function request(string $method, string $url, array $headers = [], ?string $body = null): HttpResponse
    {
        foreach ($this->responses as $needle => $response) {
            if (str_contains($url, $needle)) {
                return $response;
            }
        }

        throw new RuntimeException(sprintf('FakeHttpClient has no response for "%s %s".', $method, $url));
    }
}
