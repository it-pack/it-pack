<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\Enterprise\Http;

use JsonException;

/**
 * Transport-agnostic HTTP response value object.
 */
final readonly class HttpResponse
{
    public function __construct(
        public int    $status,
        public string $body,
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /**
     * @return mixed
     * @throws JsonException when the body is not valid JSON.
     */
    public function json(): mixed
    {
        return json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
    }
}
