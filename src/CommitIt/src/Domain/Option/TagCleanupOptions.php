<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Option;

use DateTimeImmutable;

/**
 * Immutable configuration for {@see \ItPack\CommitIt\Contract\TagPort::cleanupTags()}.
 *
 * @api
 */
final readonly class TagCleanupOptions
{
    private function __construct(
        public readonly ?\DateTimeImmutable $before,
        public readonly ?string $pattern,
        public readonly bool $force,
    ) {
    }

    public static function default(): self
    {
        return new self(null, null, false);
    }

    /**
     * Remove tags whose commit date is before the given time.
     */
    public function olderThan(DateTimeImmutable $before): self
    {
        return new self($before, $this->pattern, $this->force);
    }

    /**
     * Remove only tags matching the given glob pattern (e.g. `v*-old`).
     */
    public function matchingPattern(string $pattern): self
    {
        return new self($this->before, $pattern, $this->force);
    }

    /**
     * Force removal even for annotated tags.
     */
    public function force(bool $force = true): self
    {
        return new self($this->before, $this->pattern, $force);
    }
}
