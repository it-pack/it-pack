<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Option;

/**
 * Immutable query for {@see \ItPack\CommitIt\Contract\BranchPort::searchBranches()}.
 *
 * @api
 */
final readonly class BranchQuery
{
    private function __construct(
        public readonly ?string $pattern,
        public readonly bool $currentOnly,
        public readonly bool $remoteOnly,
        public readonly ?string $containsCommit,
        public readonly ?string $mergedInto,
    ) {
    }

    public static function all(): self
    {
        return new self(null, false, false, null, null);
    }

    public function withPattern(string $pattern): self
    {
        return new self($pattern, $this->currentOnly, $this->remoteOnly, $this->containsCommit, $this->mergedInto);
    }

    public function currentOnly(bool $only = true): self
    {
        return new self($this->pattern, $only, $this->remoteOnly, $this->containsCommit, $this->mergedInto);
    }

    public function remoteOnly(bool $only = true): self
    {
        return new self($this->pattern, $this->currentOnly, $only, $this->containsCommit, $this->mergedInto);
    }

    /**
     * Limit results to branches that contain the given commit.
     */
    public function containsCommit(string $commitId): self
    {
        return new self($this->pattern, $this->currentOnly, $this->remoteOnly, $commitId, $this->mergedInto);
    }

    /**
     * Limit results to branches merged into the given commit or ref.
     */
    public function mergedInto(string $commitId): self
    {
        return new self($this->pattern, $this->currentOnly, $this->remoteOnly, $this->containsCommit, $commitId);
    }
}
