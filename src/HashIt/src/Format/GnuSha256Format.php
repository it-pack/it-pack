<?php

declare(strict_types=1);

namespace ItPack\HashIt\Format;

use InvalidArgumentException;
use ItPack\HashIt\Hash;
use ItPack\HashIt\HashAlgorithm;
use ItPack\HashIt\HashManifest;

/**
 * GNU `sha256sum` text format. Lines are `<hexdigest>  <path>` (text mode, two
 * spaces) or `<hexdigest> *<path>` (binary mode, space + asterisk). The algorithm
 * is fixed to SHA256 — this format has no algorithm field — so serializing a
 * manifest with any other algorithm is rejected, and parsing always yields SHA256.
 */
final class GnuSha256Format implements ManifestFormatInterface
{
    public function serialize(HashManifest $manifest): string
    {
        if ($manifest->algorithm() !== HashAlgorithm::SHA256) {
            throw new InvalidArgumentException('GnuSha256Format only supports the SHA-256 algorithm');
        }

        $lines = [];
        foreach ($manifest->entries() as $path => $hash) {
            $lines[] = $hash->value() . '  ' . $path;
        }

        return implode("\n", $lines) . "\n";
    }

    public function parse(string $text): HashManifest
    {
        $manifest = new HashManifest(HashAlgorithm::SHA256);

        foreach ($this->lines($text) as $line) {
            if (!preg_match('/^([0-9a-f]+) \*(.+)$/', $line, $m)
                && !preg_match('/^([0-9a-f]+)  (.+)$/', $line, $m)) {
                throw new InvalidArgumentException('Malformed GNU sha256sum line: ' . $line);
            }

            $manifest = $manifest->with($m[2], $this->hash(strtolower($m[1])));
        }

        return $manifest;
    }

    /**
     * @return iterable<string>
     */
    private function lines(string $text): iterable
    {
        foreach (explode("\n", rtrim($text, "\n")) as $line) {
            if ($line === '') {
                continue;
            }
            yield $line;
        }
    }

    private function hash(string $hex): Hash
    {
        return Hash::create($hex, HashAlgorithm::SHA256->value);
    }
}
