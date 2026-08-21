<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Value;

/**
 * Validated, opaque repository path.
 *
 * Skeleton for Milestone M1: stores the path and enforces non-empty. Path
 * resolution/scope checks are added in later milestones.
 *
 * @api
 */
final class RepositoryPath
{
    private function __construct(public readonly string $path)
    {
    }

    public static function from(string $path): self
    {
        $trimmed = trim($path);

        if (trim($trimmed) === '') {
            throw new \InvalidArgumentException('Repository path must not be empty.');
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
}
