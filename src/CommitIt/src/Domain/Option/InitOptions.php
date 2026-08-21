<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Option;

/**
 * Immutable configuration for {@see \ItPack\CommitIt\Contract\RepositoryFactory::init()}.
 *
 * @api
 */
final class InitOptions
{
    private function __construct(
        public readonly ?string $initialBranch,
        public readonly bool $bare,
    ) {
    }

    public static function default(): self
    {
        return new self(null, false);
    }

    public function withInitialBranch(string $branch): self
    {
        return new self($branch, $this->bare);
    }

    public function withBare(bool $bare = true): self
    {
        return new self($this->initialBranch, $bare);
    }
}
