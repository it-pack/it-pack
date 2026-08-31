<?php

declare(strict_types=1);

namespace ItPack\HashIt\Format;

use InvalidArgumentException;
use ItPack\HashIt\Hash;
use ItPack\HashIt\HashAlgorithm;
use ItPack\HashIt\HashManifest;

/**
 * BSD `md5`/`sha*` text format: `<ALGO> (path) = <hexdigest>`, e.g.
 * `SHA256 (file.txt) = <hex>`. The algorithm is carried in the prefix, so any
 * supported algorithm round-trips (not just SHA-256).
 */
final class BsdFormat implements ManifestFormatInterface
{
    public function serialize(HashManifest $manifest): string
    {
        $lines = [];
        foreach ($manifest->entries() as $path => $hash) {
            $lines[] = strtoupper($manifest->algorithm()->value) . ' (' . $path . ') = ' . $hash->value();
        }

        return implode("\n", $lines) . "\n";
    }

    public function parse(string $text): HashManifest
    {
        $manifest = null;

        foreach ($this->lines($text) as $line) {
            if (!preg_match('/^(\S+) \(([^)]*)\) = ([0-9a-fA-F]+)$/', $line, $m)) {
                throw new InvalidArgumentException('Malformed BSD line: ' . $line);
            }

            $algorithm = HashAlgorithm::tryFrom(strtolower($m[1]));
            if ($algorithm === null) {
                throw new InvalidArgumentException('Unknown algorithm in BSD line: ' . $m[1]);
            }

            if ($manifest === null) {
                $manifest = new HashManifest($algorithm);
            } elseif ($manifest->algorithm() !== $algorithm) {
                throw new InvalidArgumentException('BSD manifest mixes algorithms: ' . $m[1]);
            }

            $manifest = $manifest->with($m[2], Hash::create(strtolower($m[3]), $algorithm->value));
        }

        if ($manifest === null) {
            throw new InvalidArgumentException('No BSD entries found');
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
}
