<?php

declare(strict_types=1);

namespace ItPack\ConfigureItExplorer\Server;

/**
 * An immutable representation of an incoming HTTP request.
 *
 * Constructed either from PHP globals ({@see fromGlobals}) for the built-in
 * server, or directly in tests. Only the fields the explorer needs are modelled.
 */
final readonly class Request
{
    /**
     * @param array<string, string> $query  parsed query string (GET parameters)
     * @param array<string, mixed>  $server raw $_SERVER-like map
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $query,
        public array $server = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = \is_string($uri) ? $uri : '/';
        $path = \parse_url($uri, PHP_URL_PATH);

        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        $queryString = \is_string($queryString) ? $queryString : '';
        \parse_str($queryString, $parsed);

        $query = [];

        foreach ($parsed as $key => $value) {
            $query[(string) $key] = \is_string($value) ? $value : '';
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $method = \is_string($method) ? $method : 'GET';

        return new self(
            method: \strtoupper($method),
            path: \is_string($path) && '' !== $path ? $path : '/',
            query: $query,
            server: $_SERVER,
        );
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->query[$key] ?? $default;
    }

    public function wantsJson(): bool
    {
        $accept = $this->server['HTTP_ACCEPT'] ?? '';
        $accept = \is_string($accept) ? $accept : '';

        return 'json' === $this->get('format') || \str_contains($accept, 'application/json');
    }
}
