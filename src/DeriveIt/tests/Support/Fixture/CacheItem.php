<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use Psr\Cache\CacheItemInterface;

/**
 * Minimal in-memory PSR-6 cache item for adapter testing
 * (R4-7 / F-5 — extracted from Phase15BuildMetaAndCacheTest).
 */
final class CacheItem implements CacheItemInterface
{
    public function __construct(
        private string $key,
        private mixed $value = null,
        private bool $hit = true,
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        return $this->hit;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;
        $this->hit = true;

        return $this;
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        return $this;
    }

    public function expiresAfter(int|\DateInterval|null $time): static
    {
        return $this;
    }
}
