<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Option;

/**
 * Immutable configuration for {@see \ItPack\CommitIt\Contract\RepositoryFactory::cloneRepository()}.
 *
 * @api
 */
final readonly class CloneOptions
{
    private function __construct(
        public readonly ?string $branch,
        public readonly bool $bare,
        public readonly ?int $depth,
    ) {
    }

    public static function default(): self
    {
        return new self(null, false, null);
    }

    public function withBranch(?string $branch): self
    {
        return new self($branch, $this->bare, $this->depth);
    }

    public function withBare(bool $bare = true): self
    {
        return new self($this->branch, $bare, $this->depth);
    }

    public function withDepth(?int $depth): self
    {
        return new self($this->branch, $this->bare, $depth);
    }
}
