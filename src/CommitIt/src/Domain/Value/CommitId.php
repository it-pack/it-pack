<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Value;

/**
 * Validated, opaque commit identifier.
 *
 * The value is a non-empty opaque string. Its exact shape (git: 40-hex SHA-1) is
 * enforced at the adapter boundary, not in the domain; the domain only guarantees
 * non-emptiness and value equality.
 *
 * @api
 */
final readonly class CommitId
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
