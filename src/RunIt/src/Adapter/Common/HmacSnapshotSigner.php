<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Common;

use ItPack\RunIt\Contract\Port\SnapshotSignerInterface;

/**
 * HMAC-SHA256 implementation of the optional snapshot signer port.
 *
 * It signs the canonical snapshot content with a shared secret and verifies the
 * signature with a constant-time comparison. This adapter is reference-grade: a
 * production deployment would typically keep the key in a secret store and verify
 * at the storage boundary, not inside the hot path. Signing is opt-in and never
 * required for `1.0.0`.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Snapshot
 */
final readonly class HmacSnapshotSigner implements SnapshotSignerInterface
{
    public function __construct(
        private string $key,
        private string $algorithm = 'sha256',
    ) {
    }

    public function sign(string $content): string
    {
        return hash_hmac($this->algorithm, $content, $this->key);
    }

    public function verify(string $content, string $signature): bool
    {
        return hash_equals($this->sign($content), $signature);
    }
}
