<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Value;

/**
 * Validated branch name.
 *
 * Skeleton for Milestone M1: stores the name and enforces non-empty. Full
 * ref-name rules (illegal characters, `..` sequences, etc.) are enforced by
 * {@see \ItPack\CommitIt\Domain\Validation\RefNameValidator} in Milestone M2.
 *
 * @api
 */
final class BranchName
{
    private function __construct(public readonly string $name)
    {
    }

    public static function from(string $name): self
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            throw new \InvalidArgumentException('Branch name must not be empty.');
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
