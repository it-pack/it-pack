<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Exception;

/**
 * Raised when a secret cannot be decrypted.
 *
 * A decryption failure must never surface a partially-decrypted secret or fall
 * back to the ciphertext; the resolver fails fast (M4 — Epic E5.1).
 */
final class DecryptionException extends ConfigurationException
{
}
