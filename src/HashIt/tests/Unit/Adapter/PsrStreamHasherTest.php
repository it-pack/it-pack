<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\HashIt\Adapter\NativeContextFactory;
use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\Adapter\PsrStreamHasher;
use ItPack\HashIt\Contract\StreamHasherInterface;
use ItPack\HashIt\Factory\HashFactory;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\StreamInterface;

class PsrStreamHasherTest extends Unit
{
    private function hasher(): PsrStreamHasher
    {
        return new PsrStreamHasher(new NativeContextFactory(), new HashFactory());
    }

    public function testHashesPsrStreamEqualToPlainStringHash(): void
    {
        $content = 'the quick brown fox jumps over the lazy dog';
        $stream = Stream::create($content);

        $hash = $this->hasher()->hash($stream, 'sha256');

        verify($hash->value())->equals(NativeHashIt::create()->hash($content)->value());
        verify($hash->algorithm())->equals('sha256');
    }

    public function testReadsInChunks(): void
    {
        $content = str_repeat('abcdefgh', 64); // 512 bytes
        $spy = new SpyStream(Stream::create($content));

        $this->hasher()->hash($spy, 'sha256', 17);

        // 512 / 17 -> ceil -> several reads, proving chunked (not single) reading
        verify($spy->reads)->greaterThan(1);
    }

    public function testStreamRemainsUsableAfterHashing(): void
    {
        $content = 'keep the stream intact after reading';
        $spy = new SpyStream(Stream::create($content));

        $this->hasher()->hash($spy, 'sha256');

        // never detached: rewind + re-read yields the original content
        $spy->rewind();
        verify($spy->getContents())->equals($content);
    }

    public function testRejectsNonReadableStream(): void
    {
        $stream = new NonReadableStream();

        $this->expectException(\InvalidArgumentException::class);

        $this->hasher()->hash($stream, 'sha256');
    }

    public function testRejectsNonPositiveChunkSize(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->hasher()->hash(Stream::create('x'), 'sha256', 0);
    }

    public function testHashesEmptyStreamToDigestOfEmptyString(): void
    {
        $hash = $this->hasher()->hash(Stream::create(''), 'sha256');

        verify($hash->value())->equals(hash('sha256', ''));
    }

    public function testImplementsStreamHasherInterface(): void
    {
        self::assertInstanceOf(StreamHasherInterface::class, NativeHashIt::streamHasher());
    }

    /**
     * Contract test: PsrStreamHasher (via StreamHasherInterface) must produce
     * byte-identical digests to Hasher::hashStream() for the same stream content.
     * Uses chunked content to exercise the incremental-reading path.
     */
    public function testContractProducesIdenticalDigestToHasherStream(): void
    {
        $content = str_repeat('contract-test-data', 800);

        $viaPsr = NativeHashIt::streamHasher()->hash(Stream::create($content), 'sha256');

        $resource = fopen('php://memory', 'r+b');
        fwrite($resource, $content);
        rewind($resource);
        $viaHasher = NativeHashIt::create()->hashStream($resource, 'sha256');
        fclose($resource);

        verify($viaPsr->value())->equals($viaHasher->value());
        verify($viaPsr->value())->equals(hash('sha256', $content));
    }

    public function testContractProducesIdenticalDigestAcrossChunkSizes(): void
    {
        $content = str_repeat('multi-chunk-content', 200);

        $reference = hash('sha256', $content);

        foreach ([1, 17, 64, 1024, 65536] as $chunkSize) {
            $stream = Stream::create($content);
            $hash = NativeHashIt::streamHasher()->hash($stream, 'sha256', $chunkSize);

            verify($hash->value())->equals($reference);
        }
    }

    public function testContractSupportsMultipleAlgorithms(): void
    {
        $content = 'algorithm-variants';

        foreach (['sha256', 'sha384', 'sha512'] as $algo) {
            $stream = Stream::create($content);
            $hash = NativeHashIt::streamHasher()->hash($stream, $algo);

            verify($hash->value())->equals(hash($algo, $content));
            verify($hash->algorithm())->equals($algo);
        }
    }
}

/**
 * Minimal PSR-7 stream that reports itself as not readable, so we can drive
 * {@see PsrStreamHasher::hash()} down the "not readable" guard.
 */
final class NonReadableStream implements StreamInterface
{
    public function __toString(): string
    {
        return '';
    }

    public function close(): void
    {
    }

    public function detach()
    {
        return null;
    }

    public function getSize(): int
    {
        return 0;
    }

    public function tell(): int
    {
        return 0;
    }

    public function eof(): bool
    {
        return true;
    }

    public function isSeekable(): bool
    {
        return false;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
    }

    public function rewind(): void
    {
    }

    public function isWritable(): bool
    {
        return true;
    }

    public function write(string $string): int
    {
        return strlen($string);
    }

    public function isReadable(): bool
    {
        return false;
    }

    public function read(int $length): string
    {
        return '';
    }

    public function getContents(): string
    {
        return '';
    }

    public function getMetadata($key = null)
    {
        return null;
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
