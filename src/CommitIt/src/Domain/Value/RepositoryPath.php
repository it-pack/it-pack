<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Value;

/**
 * Validated, opaque repository path.
 *
 * Holds a path validated as non-empty and free of control characters. Filesystem
 * resolution is deferred: {@see self::realPath} resolves the canonical path on
 * demand (and caches it), keeping construction pure and side-effect free.
 *
 * @api
 */
final class RepositoryPath
{
    private ?string $resolvedRealPath = null;

    private function __construct(public readonly string $path)
    {
    }

    public static function from(string $path): self
    {
        $trimmed = trim($path);

        if ($trimmed === '') {
            throw new \InvalidArgumentException('Repository path must not be empty.');
        }

        if (preg_match('/[\x00-\x1f\x7f]/', $trimmed) === 1) {
            throw new \InvalidArgumentException('Repository path must not contain control characters.');
        }

        return new self($trimmed);
    }

    public function toString(): string
    {
        return $this->path;
    }

    public function __toString(): string
    {
        return $this->path;
    }

    /**
     * Canonical, resolved filesystem path. Resolution happens on first access and
     * is cached; for a path that does not exist the original path is returned.
     */
    public string $realPath {
        get {
            if ($this->resolvedRealPath !== null) {
                return $this->resolvedRealPath;
            }

            $real = realpath($this->path);

            return $this->resolvedRealPath = ($real === false ? $this->path : $real);
        }
    }
}
