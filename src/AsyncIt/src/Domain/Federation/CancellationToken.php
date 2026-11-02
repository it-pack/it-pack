<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain\Federation;

use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\ValueObjectInterface;
use Stringable;

/**
 * Serializable cancellation token propagated across federation boundaries.
 *
 * A CancellationToken captures the essential state of a {@see CancellationScope}
 * (scope id and optional cancellation reason) in a serializable form so it can
 * be embedded in a RemoteTask payload and sent to a remote scheduler. The remote
 * side can deserialize it and observe whether cancellation was requested.
 *
 * @see https://w3id.org/itpack/asyncit#CancellationToken
 * @see https://w3id.org/itpack/asyncit#CancellationScope
 * @see https://w3id.org/itpack/asyncit#hasCancellationReason
 */
final readonly class CancellationToken implements ValueObjectInterface, Stringable, CancellationTokenInterface
{
    public function __construct(
        public string $scopeId,
        public ?string $reason = null,
        public bool $cancelled = false,
    ) {
    }

    public function __toString(): string
    {
        return $this->scopeId;
    }

    public static function fromCancellationScope(CancellationScopeInterface $scope, string $scopeId): self
    {
        return new self(
            $scopeId,
            $scope->reason()?->value,
            $scope->isCancelled(),
        );
    }

    public function scopeId(): string
    {
        return $this->scopeId;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }
}
