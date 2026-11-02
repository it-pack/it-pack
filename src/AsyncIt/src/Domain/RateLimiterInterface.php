<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain;

use ItPack\RunIt\Contract\Domain\DurationInterface;

/**
 * Token-bucket rate limiter surface using a monotonic clock.
 *
 * Backs {@see RateLimiter} so rate limiting remains mockable and interface-
 * driven.
 *
 * @see https://w3id.org/itpack/asyncit#RateLimiter
 * @api
 */
interface RateLimiterInterface
{
    /**
     * Attempts to consume a token without blocking.
     */
    public function tryConsume(): bool;

    /**
     * Computes the delay needed to acquire a token. Returns zero when a token
     * is immediately available.
     */
    public function waitForPermit(): DurationInterface;

    /**
     * Consumes a token immediately. Throws if none available.
     */
    public function consume(): void;
}
