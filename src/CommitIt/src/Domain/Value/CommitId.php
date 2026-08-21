<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Value;

/**
 * Validated, opaque commit identifier.
 *
 * Skeleton for Milestone M1: opaque string (git: 40-hex SHA-1). Backend-specific
 * format validation is enforced by the adapter and the dedicated validators in
 * Milestone M2.
 *
 * @api
 */
final class CommitId
{
    private function __construct(public readonly string $value)
    {
    }

    public static function from(string $value): self
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new \InvalidArgumentException('Commit id must not be empty.');
        }

        return new self($trimmed);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
