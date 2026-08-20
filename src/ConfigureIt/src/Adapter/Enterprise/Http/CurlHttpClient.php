<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\Enterprise\Http;

use ItPack\ConfigureIt\Adapter\Enterprise\Contract\HttpClient;

/**
 * cURL-backed {@see HttpClient} for production use.
 *
 * Performs no authentication — signing belongs in the caller (the cloud clients
 * pass signed headers) or in a decorating transport. Failures raise
 * {@see \RuntimeException} so the resolver can surface them as read errors.
 */
final readonly class CurlHttpClient implements HttpClient
{
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

        $headerLines = [];

        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $options = [
            CURLOPT_CUSTOMREQUEST => $upperMethod,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FAILONERROR => false,
        ];

        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($handle, $options);

        $responseBody = curl_exec($handle);
        $status = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error = curl_error($handle);

        curl_close($handle);

        if ($responseBody === false) {
            throw new \RuntimeException(sprintf('HTTP "%s %s" failed: %s', $upperMethod, $url, $error));
        }

        return new HttpResponse($status, (string) $responseBody);
    }
}
