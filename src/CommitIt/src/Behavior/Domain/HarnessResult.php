<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Behavior\Domain;

use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Immutable result collection returned by {@see RepositoryBehaviorHarness}.
 *
 * Adapter authors can inspect `passed()` and `violations()` to verify
 * their implementation. Zero violations means full contract compliance.
 *
 * @api
 *
 * @implements \IteratorAggregate<int, string>
 */
final readonly class HarnessResult implements IteratorAggregate, Countable
{
    /**
     * @param list<string> $violations
     */
    public function __construct(
        public array $violations,
    ) {
    }

    public function passed(): bool
    {
        return $this->violations === [];
    }

    /**
     * @return Traversable<int, string>
     */
    public function getIterator(): Traversable
    {
        yield from $this->violations;
    }

    public function count(): int
    {
        return count($this->violations);
    }
}
