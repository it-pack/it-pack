<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\Decryption;

use ItPack\ConfigureIt\Contract\ConfigurationReaderInterface;
use ItPack\ConfigureIt\Contract\DecrypterInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ConfigurationResourceInterface;
use ItPack\ConfigureIt\Exception\ReadException;
use Throwable;

/**
 * Reader decorator that transparently decrypts annotated values.
 *
 * Wraps any {@see ConfigurationReaderInterface}. When the raw payload begins
 * with the `enc:` or `sops:` marker, the marker is stripped and the remaining
 * ciphertext is decrypted via the injected {@see DecrypterInterface}; the
 * decrypted payload (e.g. JSON/YAML) is then returned for the loader to parse.
 * Resources without the marker pass through untouched, so this decorator is
 * safe to place in front of every reader (M4 — Epic E5.1).
 */
final class DecryptingReader implements ConfigurationReaderInterface
{
    private const MARKERS = [
        'enc:' => 4,
        'sops:' => 5,
    ];

    public function __construct(
        private ConfigurationReaderInterface $inner,
        private DecrypterInterface           $decrypter,
    ) {
    }

    public function read(ConfigurationResourceInterface $resource): string
    {
        $raw = $this->inner->read($resource);

        $trimmed = ltrim($raw);

        foreach (self::MARKERS as $marker => $length) {
            if (str_starts_with(strtolower($trimmed), $marker)) {
                try {
                    return $this->decrypter->decrypt(substr($trimmed, $length));
                } catch (Throwable $e) {
                    throw new ReadException(
                        sprintf('Failed to decrypt resource "%s": %s', $resource->uri(), $e->getMessage()),
                        0,
                        $e,
                    );
                }
            }
        }

        return $raw;
    }

    public function supports(ConfigurationResourceInterface $resource): bool
    {
        return $this->inner->supports($resource);
    }
}
