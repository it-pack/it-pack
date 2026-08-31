<?php

declare(strict_types=1);

namespace ItPack\HashIt;

/**
 * A plain filesystem path the core can construct without reaching into the
 * {@see Contract\FilepathInterface} adapter (which is walled off behind the composition
 * root). Used by {@see ManifestVerifier} to turn a manifest's stored path
 * string back into the {@see Contract\FilepathInterface} the hasher expects.
 */
final readonly class Filepath implements Contract\FilepathInterface
{
    public function __construct(private string $path)
    {
    }

    public function __toString(): string
    {
        return $this->path;
    }
}
