<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Value;

/**
 * Validated tag name.
 *
 * Skeleton for Milestone M1: stores the name and enforces non-empty. Full
 * ref-name rules are enforced by the dedicated validator in Milestone M2.
 *
 * @api
 */
final class TagName
{
    private function __construct(public readonly string $name)
    {
    }

    public static function from(string $name): self
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            throw new \InvalidArgumentException('Tag name must not be empty.');
        }

        return new self($trimmed);
    }

    public function equals(self $other): bool
    {
        return $this->name === $other->name;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
