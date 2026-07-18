<?php

declare(strict_types=1);

namespace ItPack\HashIt;

/**
 * Immutable incremental hashing builder. Each append returns a new builder with
 * a copied context, so the original is never mutated — safe to share and reuse.
 */
final class HashBuilder
{
    private function __construct(
        private readonly HashContextInterface $context,
        private readonly string $algorithm,
        private readonly bool $binary,
    ) {
    }

    public static function start(
        ContextFactoryInterface $factory,
        string|HashAlgorithm $algorithm = 'sha256',
        bool $binary = false,
    ): self {
        $algorithm = $algorithm instanceof HashAlgorithm ? $algorithm->value : $algorithm;

        return new self($factory->build($algorithm), $algorithm, $binary);
    }

    public function append(string $data): self
    {
        $next = $this->context->copy();
        $next->update($data);

        return new self($next, $this->algorithm, $this->binary);
    }

    public function digest(): HashInterface
    {
        return new Hash($this->context->copy()->finalize($this->binary), $this->algorithm, $this->binary);
    }
}
