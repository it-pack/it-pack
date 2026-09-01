<?php

declare(strict_types=1);

namespace ItPack\HashIt;

use ItPack\HashIt\Factory\HashFactory;

/**
 * Immutable incremental hashing builder. Each append returns a new builder with
 * a copied context, so the original is never mutated — safe to share and reuse.
 */
final readonly class HashBuilder implements Contract\HashSinkInterface
{
    private function __construct(
        private Contract\HashContextInterface $context,
        private HashFactory $hashFactory,
        private string               $algorithm,
        private bool                 $binary,
    ) {
    }

    public static function start(
        Contract\ContextFactoryInterface $factory,
        HashFactory $hashFactory,
        string|HashAlgorithm $algorithm = HashAlgorithm::SHA256,
        bool $binary = false,
    ): self {
        $algorithm = $algorithm instanceof HashAlgorithm ? $algorithm->value : $algorithm;

        return new self($factory->build($algorithm), $hashFactory, $algorithm, $binary);
    }

    /**
     * Start from a context that has already been initialized (e.g. a keyed HMAC
     * context from {@see Contract\KeyedContextFactoryInterface}), so callers can feed the
     * immutable builder without re-deriving the algorithm configuration.
     */
    public static function fromContext(
        Contract\HashContextInterface $context,
        HashFactory $hashFactory,
        string|HashAlgorithm $algorithm = HashAlgorithm::SHA256,
        bool $binary = false,
    ): self {
        $algorithm = $algorithm instanceof HashAlgorithm ? $algorithm->value : $algorithm;

        return new self($context, $hashFactory, $algorithm, $binary);
    }

    public function append(string $data): self
    {
        $next = $this->context->copy();
        $next->update($data);

        return new self($next, $this->hashFactory, $this->algorithm, $this->binary);
    }

    public function digest(): Contract\HashInterface
    {
        return $this->hashFactory->create(
            $this->context->copy()->finalize($this->binary),
            $this->algorithm,
            $this->binary,
        );
    }
}
