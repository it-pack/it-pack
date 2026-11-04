<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Value;

use ItPack\CommitIt\Domain\Validation\RefKind;
use ItPack\CommitIt\Domain\Validation\RefNameValidator;

/**
 * Validated branch name. Backed by {@see RefNameValidator} (git ref-format
 * rules). Immutable and comparable by value.
 *
 * @api
 */
final readonly class BranchName
{
    private function __construct(public string $name)
    {
    }

    public static function from(string $name): self
    {
        RefNameValidator::assertValid($name, RefKind::Branch);

        return new self($name);
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
