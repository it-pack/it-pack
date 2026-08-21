<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\Enterprise;

use ItPack\ConfigureIt\Adapter\Enterprise\Contract\HttpClient;
use ItPack\ConfigureIt\Contract\ConfigurationReaderInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ConfigurationResourceInterface;
use ItPack\ConfigureIt\Exception\ReadException;
use ItPack\ConfigureIt\ValueObject\Format;

/**
 * Reads a `http(s)://` JSON resource via an injected
 * {@see HttpClient} and returns the raw body for the
 * {@see \ItPack\ConfigureIt\Adapter\JsonLoader}.
 *
 * Knows how to fetch; never parses format. Implements the `ConfigurationReader`
 * port. Failures surface as {@see ReadException} so the resolver (and a wrapping
 * {@see \ItPack\ConfigureIt\Adapter\CachingReader}) can react. Optional default
 * headers carry auth/signing (AWS SigV4, Azure HMAC, bearer tokens).
 *
 * @see docs/ADDITIONAL_FEATURES_PLAN.md §6 "Remote & Distributed Configuration"
 */
final readonly class HttpReader implements ConfigurationReaderInterface
{
    /**
     * @param array<string, string> $headers default headers sent with every request.
     */
    public function __construct(
        private HttpClient $http,
        private array      $headers = [],
    ) {
    }

    public function read(ConfigurationResourceInterface $resource): string
    {
        if (!$this->supports($resource)) {
            throw new ReadException(sprintf('HttpReader cannot read resource "%s".', $resource->uri()));
        }

        $response = $this->http->request('GET', $resource->uri(), $this->headers);

        if (!$response->isSuccess()) {
            throw new ReadException(sprintf('HTTP GET "%s" failed: %d.', $resource->uri(), $response->status));
        }

        return $response->body;
    }

    public function supports(ConfigurationResourceInterface $resource): bool
    {
        $uri = $resource->uri();

        return $resource->format() === Format::JSON
            && (str_starts_with($uri, 'http://') || str_starts_with($uri, 'https://'));
    }
}
