<?php

declare(strict_types=1);

namespace ItPack\HashIt\Format;

use InvalidArgumentException;
use ItPack\HashIt\Contract\ManifestFormatInterface;
use ItPack\HashIt\Factory\HashFactory;
use ItPack\HashIt\Factory\ManifestFactory;
use ItPack\HashIt\HashAlgorithm;
use ItPack\HashIt\HashManifest;

/**
 * BSD `md5`/`sha*` text format: `<ALGO> (path) = <hexdigest>`, e.g.
 * `SHA256 (file.txt) = <hex>`. The algorithm is carried in the prefix, so any
 * supported algorithm round-trips (not just SHA-256).
 */
final readonly class BsdFormat implements ManifestFormatInterface
{
    public function __construct(
        private HashFactory     $hashFactory,
        private ManifestFactory $manifestFactory,
    ) {
    }

    public function serialize(HashManifest $manifest): string
    {
        return implode(
            "\n",
            array_map(
                fn ($path, $hash) => sprintf(
                    '%s (%s) = %s',
                    strtoupper(
                        $manifest
                            ->algorithm()
                            ->value
                    ),
                    $path,
                    $hash->value()
                ),
                array_keys(
                    $manifest->entries()
                ),
                $manifest->entries()
            )
        ) . "\n";
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

            if ($manifest !== null && $manifest->algorithm() !== $algorithm) {
                throw new InvalidArgumentException('BSD manifest mixes algorithms: ' . $m[1]);
            }

            if ($manifest === null) {
                $manifest = $this->manifestFactory->createManifest($algorithm);
            }
            $manifest = $m[3]
                    |> strtolower(...)
                    |> (fn ($x) => $this->hashFactory
                        ->create($x, $algorithm->value))
                    |> (static fn ($x) => $manifest
                        ->with($m[2], $x));
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
