<?php

declare(strict_types=1);

namespace ItPack\HashIt;

/**
 * Immutable incremental hashing builder. Each append returns a new builder with
 * a copied context, so the original is never mutated — safe to share and reuse.
 */
final readonly class HashBuilder implements HashSinkInterface
{
    private function __construct(
        private HashContextInterface $context,
        private string               $algorithm,
        private bool                 $binary,
    ) {
    }

    public static function start(
        ContextFactoryInterface $factory,
        string|HashAlgorithm $algorithm = HashAlgorithm::SHA256,
        bool $binary = false,
    ): self {
        $algorithm = $algorithm instanceof HashAlgorithm ? $algorithm->value : $algorithm;

        return new self($factory->build($algorithm), $algorithm, $binary);
    }

    /**
     * Start from a context that has already been initialized (e.g. a keyed HMAC
     * context from {@see KeyedContextFactoryInterface}), so callers can feed the
     * immutable builder without re-deriving the algorithm configuration.
     */
    public static function fromContext(
        HashContextInterface $context,
        string|HashAlgorithm $algorithm = HashAlgorithm::SHA256,
        bool $binary = false,
    ): self {
        $algorithm = $algorithm instanceof HashAlgorithm ? $algorithm->value : $algorithm;

        return new self($context, $algorithm, $binary);
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
