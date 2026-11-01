<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain\Federation;

use Stringable;

/**
 * Serializable cancellation token surface propagated across federation
 * boundaries.
 *
 * Backs {@see CancellationToken} so cancellation tokens remain mockable and
 * interface-driven.
 *
 * @see https://w3id.org/itpack/asyncit#CancellationToken
 * @api
 */
interface CancellationTokenInterface extends Stringable
{
    /**
     * Returns the scope id.
     */
    public function scopeId(): string;

    /**
     * Returns the cancellation reason, or null when not cancelled.
     */
    public function reason(): ?string;

    /**
     * Whether cancellation was requested.
     */
    public function isCancelled(): bool;
}
