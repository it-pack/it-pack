<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\CLI;

use function fopen;
use function fwrite;

use InvalidArgumentException;

use function is_resource;

/**
 * Minimal CLI output sink.
 *
 * Wraps a writable stream (stdout in production, an in-memory stream in tests) so
 * the CLI commands stay framework-free and unit-testable without spawning a
 * subprocess (Roadmap Phase 7).
 *
 * @see docs/architecture/GRAPH.md "Developer Experience"
 */
final class Output
{
    /**
     * @var resource
     */
    private mixed $stream;

    /**
     * @param mixed $stream a writable stream resource.
     */
    public function __construct(mixed $stream)
    {
        if (!is_resource($stream)) {
            throw new InvalidArgumentException('Output requires a stream resource.');
        }

        $this->stream = $stream;
    }

    public static function stdout(): self
    {
        return new self(fopen('php://stdout', 'w'));
    }

    public function write(string $text): void
    {
        fwrite($this->stream, $text);
    }

    public function writeln(string $text = ''): void
    {
        $this->write($text . PHP_EOL);
    }

    public function stream(): mixed
    {
        return $this->stream;
    }
}
