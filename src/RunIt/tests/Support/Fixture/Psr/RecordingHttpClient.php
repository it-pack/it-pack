<?php

declare(strict_types=1);

namespace Tests\Support\Fixture\Psr;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-18 client double that records the last sent request and returns a fixed
 * response, used to assert correlation headers were injected.
 */
final class RecordingHttpClient implements ClientInterface
{
    private ?RequestInterface $lastRequest = null;

    public function __construct(private readonly ResponseInterface $response)
    {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->lastRequest = $request;

        return $this->response;
    }

    /**
     * @return array<string, string>
     */
    public function lastHeaders(): array
    {
        if ($this->lastRequest === null) {
            return [];
        }

        $headers = [];

        foreach ($this->lastRequest->getHeaders() as $name => $values) {
            $headers[(string) $name] = implode(', ', $values);
        }

        return $headers;
    }

    public function lastBody(): string
    {
        return $this->lastRequest?->getBody()->__toString() ?? '';
    }
}
