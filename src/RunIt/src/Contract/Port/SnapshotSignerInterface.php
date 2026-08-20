<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Port;

/**
 * Optional port that produces and verifies an authenticity signature for snapshot content.
 *
 * Hashing binds a snapshot to its state; signing additionally proves who produced it. Signing is
 * optional and not required for `1.0.0`: deployments that need authenticity (rather than mere
 * tamper-evidence) wire a concrete signer and verify signatures out of band. The port deliberately
 * operates on opaque content so it never depends on the snapshot domain model.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Snapshot
 */
interface SnapshotSignerInterface
{
    /**
     * Returns a deterministic signature for the given canonical content.
     */
    public function sign(string $content): string;

    /**
     * Returns true when the signature matches the content under the configured key.
     */
    public function verify(string $content, string $signature): bool;
}
