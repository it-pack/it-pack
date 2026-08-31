<?php

declare(strict_types=1);

namespace ItPack\HashIt\Adapter;

use HashContext;
use ItPack\HashIt\Contract\HashContextInterface;

final readonly class NativeHashContext implements HashContextInterface
{
    private HashContext $context;

    public function __construct(string|HashContext $algorithm)
    {
        $this->context = $algorithm instanceof HashContext ? $algorithm : hash_init($algorithm);
    }

    public function update(string $data): void
    {
        hash_update($this->context, $data);
    }

    public function finalize(bool $binary): string
    {
        return hash_final($this->context, $binary);
    }

    public function copy(): HashContextInterface
    {
        return new self(hash_copy($this->context));
    }

    public function checkpoint(): string
    {
        try {
            return serialize($this->context);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Unable to serialize the hashing context (keyed/HMAC contexts are not serializable)',
                previous: $e,
            );
        }
    }
}
