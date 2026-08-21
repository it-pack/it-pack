<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

/**
 * Decrypts a ciphertext into its clear-text secret.
 *
 * Implemented by adapters that wrap `age`, `sops` or a cloud KMS. Throws
 * {@see \ItPack\ConfigureIt\Exception\DecryptionException} on any failure so the
 * caller can fail safe (M4 — Epic E5.1).
 */
interface DecrypterInterface
{
    /**
     * @throws \ItPack\ConfigureIt\Exception\DecryptionException
     */
    public function decrypt(string $ciphertext): string;
}
