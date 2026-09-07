<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Value;

use ItPack\CommitIt\Domain\Exception\InvalidSparsePatternException;

/**
 * Validated sparse-checkout pattern for working-tree path filtering.
 *
 * Patterns follow git's sparse-checkout syntax: glob-like expressions, `!`
 * for negation, `/` for anchoring to the repository root, and `*` / `**`
 * wildcards. Validation is syntactic only — non-empty, no control characters,
 * and bounded length — so oversized or malformed patterns are rejected
 * before reaching the backend.
 *
 * @api
 */
final readonly class SparsePattern
{
    /**
     * Upper bound that prevents unbounded input from reaching git's sparse-checkout
     * machinery while comfortably accommodating realistic pattern lengths.
     */
    public const int MAX_LENGTH = 1024;

    private function __construct(
        public readonly string $pattern,
    ) {
    }

    public static function from(string $pattern): self
    {
        $trimmed = trim($pattern);

        if ($trimmed === '') {
            throw new InvalidSparsePatternException('Sparse-checkout pattern must not be empty.');
        }

        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw new InvalidSparsePatternException(
                sprintf('Sparse-checkout pattern must not exceed %d characters.', self::MAX_LENGTH),
            );
        }

        if (preg_match('/[\x00-\x1f\x7f]/', $trimmed) === 1) {
            throw new InvalidSparsePatternException('Sparse-checkout pattern must not contain control characters.');
        }

        return new self($trimmed);
    }

    public function equals(self $other): bool
    {
        return $this->pattern === $other->pattern;
    }

    public function __toString(): string
    {
        return $this->pattern;
    }
}
