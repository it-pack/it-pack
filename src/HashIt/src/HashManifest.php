<?php

declare(strict_types=1);

namespace ItPack\HashIt;

/**
 * Immutable description of an expected set of file hashes for a single algorithm,
 * the unit of work for {@see ManifestVerifier}. Paths are keys; values are the
 * expected {@see Contract\HashInterface}. Build incrementally with {@see self::with()}.
 */
final readonly class HashManifest
{
    /**
     * @param array<string, Contract\HashInterface> $entries
     */
    public function __construct(
        private HashAlgorithm $algorithm,
        private array $entries = [],
    ) {
    }

    public function algorithm(): HashAlgorithm
    {
        return $this->algorithm;
    }

    /**
     * @return array<string, Contract\HashInterface>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * Return a copy with $path mapped to $hash, replacing any prior entry.
     */
    public function with(string $path, Contract\HashInterface $hash): self
    {
        $entries = $this->entries;
        $entries[$path] = $hash;

        return new self($this->algorithm, $entries);
    }
}
