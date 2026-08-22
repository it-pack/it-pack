<?php

declare(strict_types=1);

namespace ItPack\ConfigureItExplorer\Server;

/**
 * An immutable HTTP response produced by the explorer.
 *
 * `emit()` writes headers + body and is only ever called by the front controller
 * (`public/index.php`); unit tests inspect `status`, `headers` and `body`
 * directly without touching the SAPI.
 */
final readonly class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($status, ['Content-Type' => 'text/html; charset=utf-8'], $body);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self($status, ['Content-Type' => 'application/json; charset=utf-8'], (string) \json_encode($data));
    }

    public function emit(): void
    {
        \http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            \header(\sprintf('%s: %s', $name, $value));
        }

        echo $this->body;
    }
}
