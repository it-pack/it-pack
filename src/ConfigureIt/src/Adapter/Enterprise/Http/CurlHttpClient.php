<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\Enterprise\Http;

use ItPack\ConfigureIt\Adapter\Enterprise\Contract\HttpClient;

/**
 * cURL-backed {@see HttpClient} for production use.
 *
 * Performs no authentication — signing belongs in the caller (the cloud clients
 * pass signed headers) or in a decorating transport. Redirect following is
 * deliberately disabled: callers pass authenticated headers, and following a
 * redirect would forward them to whatever host it points at; transports that
 * need redirects must implement the policy explicitly. Failures raise
 * {@see \RuntimeException} so the resolver can surface them as read errors.
 */
final readonly class CurlHttpClient implements HttpClient
{
    public const int REQUEST_TIMEOUT_SECONDS = 10;
    public const int CONNECT_TIMEOUT_SECONDS = 5;

    public function __construct(
        private readonly int $timeoutSeconds = self::REQUEST_TIMEOUT_SECONDS,
        private readonly int $connectTimeoutSeconds = self::CONNECT_TIMEOUT_SECONDS,
    ) {
    }

    /**
     * @param array<string, string> $headers
     */
    public function request(string $method, string $url, array $headers = [], ?string $body = null): HttpResponse
    {
        $upperMethod = strtoupper($method);

        if ($upperMethod === '') {
            throw new \RuntimeException('HTTP method must not be empty.');
        }

        $handle = curl_init($url);

        if ($handle === false) {
            throw new \RuntimeException(sprintf('curl_init failed for "%s".', $url));
        }

        try {
            curl_setopt_array($handle, $this->buildOptions($upperMethod, $headers, $body));

            $responseBody = curl_exec($handle);
            $status = curl_getinfo($handle, CURLINFO_HTTP_CODE);
            $error = curl_error($handle);
        } finally {
            curl_close($handle);
        }

        if ($responseBody === false) {
            throw new \RuntimeException(sprintf('HTTP "%s %s" failed: %s', $upperMethod, $url, $error));
        }

        return new HttpResponse($status, (string) $responseBody);
    }

    /**
     * Assemble the cURL option list (kept pure so tests can assert the wire
     * policy without network access).
     *
     * @param array<string, string> $headers
     * @return array<int, mixed>
     */
    private function buildOptions(string $upperMethod, array $headers, ?string $body): array
    {
        $headerLines = [];

        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $options = [
            CURLOPT_CUSTOMREQUEST => $upperMethod,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeoutSeconds,
            CURLOPT_FAILONERROR => false,
        ];

        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        return $options;
    }
}
