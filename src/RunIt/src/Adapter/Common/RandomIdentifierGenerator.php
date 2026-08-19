<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Common;

use ItPack\RunIt\Contract\Port\IdentifierGeneratorInterface;

/**
 * Default identifier generator producing UUIDv4 strings.
 *
 * UUIDv4 satisfies the opaque-identifier contract: the value is non-empty, bounded well under
 * {@see \ItPack\RunIt\Contract\Domain\StringIdentifierValidatorInterface::MAX_LENGTH}, and consists
 * only of visible ASCII characters, so it round-trips verbatim through logs, headers, and event
 * extension attributes. Generation is delegated to PHP's CSPRNG so the core takes no dependency on
 * an external randomness or UUID library (per ADR-017).
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#executionId
 */
final class RandomIdentifierGenerator implements IdentifierGeneratorInterface
{
    public function generate(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
