<?php

declare(strict_types=1);

namespace ItPack\HashIt\Format;

use InvalidArgumentException;
use ItPack\HashIt\Contract\ManifestFormatInterface;
use ItPack\HashIt\Hash;
use ItPack\HashIt\HashAlgorithm;
use ItPack\HashIt\HashManifest;

/**
 * SFV (Simple File Verification) text format: `<path> <HEX>`. SFV classically
 * carries no algorithm, so the manifest's algorithm is recorded in a leading
 * `# algorithm <value>` comment header (defaulting to SHA-256 when absent). Paths
 * must not contain whitespace (SFV convention).
 */
final class SfvFormat implements ManifestFormatInterface
{
    public function serialize(HashManifest $manifest): string
    {
        $lines = ['# algorithm ' . $manifest->algorithm()->value];

        foreach ($manifest->entries() as $path => $hash) {
            $lines[] = $path . ' ' . $hash->value();
        }

        return implode("\n", $lines) . "\n";
    }

    public function parse(string $text): HashManifest
    {
        $algorithm = HashAlgorithm::SHA256;
        $manifest = null;

        foreach ($this->lines($text) as $line) {
            if (str_starts_with($line, '#')) {
                if (preg_match('/^#\s*algorithm\s+(\S+)$/i', $line, $m)) {
                    $algorithm = HashAlgorithm::tryFrom(strtolower($m[1]))
                        ?? throw new InvalidArgumentException('Unknown algorithm in SFV header: ' . $m[1]);
                }
                continue;
            }

            if (!preg_match('/^(\S+)\s+([0-9a-fA-F]+)$/', $line, $m)) {
                throw new InvalidArgumentException('Malformed SFV line: ' . $line);
            }

            $manifest ??= new HashManifest($algorithm);
            $manifest = $manifest->with($m[1], Hash::create(strtolower($m[2]), $algorithm->value));
        }

        return $manifest ?? new HashManifest($algorithm);
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
