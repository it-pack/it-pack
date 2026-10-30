<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Interop\ReactPhp;

/**
 * Fake ReactPHP event loop for testing {@see \ItPack\AsyncIt\Adapter\Interop\ReactPhp\ReactPhpPollerBridge}.
 */
final class FakeReactLoop
{
    /** @var list<mixed> */
    public array $added = [];

    /** @var list<mixed> */
    public array $removed = [];

    public function addReadStream(mixed $stream, callable $callback): void
    {
        $this->added[] = $stream;
    }

    public function removeReadStream(mixed $stream): void
    {
        $this->removed[] = $stream;
    }
}
