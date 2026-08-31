<?php

declare(strict_types=1);

namespace ItPack\HashIt;

/**
 * Convenience no-framework implementation of {@see \ItPack\HashIt\Contract\HashProgressInterface}: adapt
 * any callable into a progress sink. Useful for tests and quick adapters.
 *
 * @final
 */
final class HashProgress implements \ItPack\HashIt\Contract\HashProgressInterface
{
    /**
     * @param callable(int $processed, ?int $total): void $callback
     */
    public static function fromCallable(callable $callback): self
    {
        return new self($callback);
    }

    /**
     * @param callable(int $processed, ?int $total): void $callback
     */
    public function __construct(
        private readonly mixed $callback,
    ) {
    }

    public function onBytes(int $processed, ?int $total): void
    {
        ($this->callback)($processed, $total);
    }
}
