<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\HashIt\Adapter\NativeContextFactory;
use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\Adapter\PsrStreamHasher;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\StreamInterface;

class PsrStreamHasherTest extends Unit
{
    public function testHashesPsrStreamEqualToPlainStringHash(): void
    {
        $content = 'the quick brown fox jumps over the lazy dog';
        $stream = Stream::create($content);

        $hash = new PsrStreamHasher(new NativeContextFactory())->hash($stream, 'sha256');

        verify($hash->value())->equals(NativeHashIt::create()->hash($content)->value());
        verify($hash->algorithm())->equals('sha256');
    }

    public function testReadsInChunks(): void
    {
        $content = str_repeat('abcdefgh', 64); // 512 bytes
        $spy = new SpyStream(Stream::create($content));

        new PsrStreamHasher(new NativeContextFactory())->hash($spy, 'sha256', 17);

        // 512 / 17 -> ceil -> several reads, proving chunked (not single) reading
        verify($spy->reads)->greaterThan(1);
    }

    public function testStreamRemainsUsableAfterHashing(): void
    {
        $content = 'keep the stream intact after reading';
        $spy = new SpyStream(Stream::create($content));

        new PsrStreamHasher(new NativeContextFactory())->hash($spy, 'sha256');

        // never detached: rewind + re-read yields the original content
        $spy->rewind();
        verify($spy->getContents())->equals($content);
    }
}

/**
 * Decorates any StreamInterface, counting calls to read() so tests can assert
 * chunked reading without depending on a specific PSR-7 implementation.
 */
final class SpyStream implements StreamInterface
{
    public int $reads = 0;

    public function __construct(
        private readonly StreamInterface $inner,
    ) {
    }

    public function read(int $length): string
    {
        $this->reads++;

        return $this->inner->read($length);
    }

    public function __toString(): string
    {
        return $this->inner->__toString();
    }

    public function close(): void
    {
        $this->inner->close();
    }

    public function detach()
    {
        return $this->inner->detach();
    }

    public function getSize(): ?int
    {
        return $this->inner->getSize();
    }

    public function tell(): int
    {
        return $this->inner->tell();
    }

    public function eof(): bool
    {
        return $this->inner->eof();
    }

    public function isSeekable(): bool
    {
        return $this->inner->isSeekable();
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $this->inner->seek($offset, $whence);
    }

    public function rewind(): void
    {
        $this->inner->rewind();
    }

    public function isWritable(): bool
    {
        return $this->inner->isWritable();
    }

    public function write(string $string): int
    {
        return $this->inner->write($string);
    }

    public function isReadable(): bool
    {
        return $this->inner->isReadable();
    }

    public function getContents(): string
    {
        return $this->inner->getContents();
    }

    public function getMetadata($key = null)
    {
        return $this->inner->getMetadata($key);
    }
}
