<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use ItPack\HashIt\Adapter\NativeFilepathFileinfo;
use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\Adapter\NativeKeyedContextFactory;
use ItPack\HashIt\Contract\HashContextInterface;
use ItPack\HashIt\Contract\HashInterface;
use ItPack\HashIt\Contract\KeyedHasherInterface;
use ItPack\HashIt\HashAlgorithm;

class KeyedHasherTest extends Unit
{
    private function hasher(): KeyedHasherInterface
    {
        return NativeHashIt::keyedHasher();
    }

    public function testCompositionRootExposesKeyedHasher(): void
    {
        self::assertInstanceOf(KeyedHasherInterface::class, NativeHashIt::keyedHasher());
    }

    public function testHmacMatchesNativeHashHmac(): void
    {
        $hash = $this->hasher()->hmac('message', 'secret', 'sha256');

        verify($hash->value())->equals(hash_hmac('sha256', 'message', 'secret'));
        verify($hash->algorithm())->equals('sha256');
        verify($hash->binary())->false();
    }

    public function testHmacAcceptsEnumAlgorithm(): void
    {
        $hash = $this->hasher()->hmac('message', 'secret', HashAlgorithm::SHA512);

        verify($hash->value())->equals(hash_hmac('sha512', 'message', 'secret'));
    }

    public function testHmacBinaryMatchesNativeHashHmacBinary(): void
    {
        $hash = $this->hasher()->hmacBinary('message', 'secret');

        verify($hash->binary())->true();
        verify($hash->value())->equals(hash_hmac('sha256', 'message', 'secret', true));
    }

    public function testHmacFileMatchesNativeHashHmac(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'hashit');
        file_put_contents($tmp, 'hello world');

        $hash = $this->hasher()->hmacFile(NativeFilepathFileinfo::of($tmp), 'secret', 'sha256');

        verify($hash->value())->equals(hash_hmac('sha256', 'hello world', 'secret'));

        unlink($tmp);
    }

    public function testHmacFileThrowsOnMissing(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->hasher()->hmacFile(NativeFilepathFileinfo::of('/nonexistent/path/xyz'), 'secret');
    }

    public function testHmacStreamMatchesNativeHashHmac(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'hashit');
        file_put_contents($tmp, 'hello world streamed');

        $stream = fopen($tmp, 'rb');
        $streamed = $this->hasher()->hmacStream($stream, 'secret');
        fclose($stream);

        verify($streamed->value())->equals(hash_hmac('sha256', 'hello world streamed', 'secret'));

        unlink($tmp);
    }

    public function testHmacStreamHandlesLargePayloadInChunks(): void
    {
        $payload = str_repeat('abcdefgh', 20000); // ~160 KiB, spans 3 chunks at 64 KiB
        $stream = fopen('php://memory', 'rb+');
        fwrite($stream, $payload);
        rewind($stream);

        $hash = $this->hasher()->hmacStream($stream, 'secret', 'sha256', 65536);
        fclose($stream);

        verify($hash->value())->equals(hash_hmac('sha256', $payload, 'secret'));
    }

    public function testHmacStreamRejectsNonResource(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $closed = tmpfile();
        fclose($closed);
        $this->hasher()->hmacStream($closed, 'secret');
    }

    public function testHmacStreamRejectsNonPositiveChunkSize(): void
    {
        $stream = fopen('php://memory', 'rb');

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->hasher()->hmacStream($stream, 'secret', 'sha256', 0);
        } finally {
            fclose($stream);
        }
    }

    public function testHmacFilesReturnsPathMap(): void
    {
        $a = tempnam(sys_get_temp_dir(), 'hashit');
        $b = tempnam(sys_get_temp_dir(), 'hashit');
        file_put_contents($a, 'file a');
        file_put_contents($b, 'file b');

        $map = $this->hasher()->hmacFiles([
            NativeFilepathFileinfo::of($a),
            NativeFilepathFileinfo::of($b),
        ], 'secret', 'sha256');

        verify(count($map))->equals(2);
        verify($map[$a]->value())->equals(hash_hmac('sha256', 'file a', 'secret'));
        verify($map[$b]->value())->equals(hash_hmac('sha256', 'file b', 'secret'));

        unlink($a);
        unlink($b);
    }

    public function testVerifyMatchesExpectedDigest(): void
    {
        $hasher = $this->hasher();
        $expected = $hasher->hmac('abc', 'secret');

        verify($hasher->verify('abc', 'secret', $expected))->true();
        verify($hasher->verify('abd', 'secret', $expected))->false();
    }

    public function testVerifyBinaryMatchesExpectedDigest(): void
    {
        $hasher = $this->hasher();
        $expected = $hasher->hmacBinary('abc', 'secret');

        verify($hasher->verifyBinary('abc', 'secret', $expected))->true();
        verify($hasher->verifyBinary('abd', 'secret', $expected))->false();
    }

    public function testHmacRejectsNonHmacSafeAlgorithm(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->hasher()->hmac('message', 'secret', HashAlgorithm::CRC32);
    }

    public function testHmacBinaryRejectsNonHmacSafeAlgorithm(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->hasher()->hmacBinary('message', 'secret', HashAlgorithm::CRC32);
    }

    public function testHmacStreamRejectsNonHmacSafeAlgorithm(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $stream = fopen('php://memory', 'rb');
        try {
            $this->hasher()->hmacStream($stream, 'secret', HashAlgorithm::CRC32);
        } finally {
            fclose($stream);
        }
    }

    public function testKeyedContextFactoryProducesIncrementallyEquivalentDigest(): void
    {
        $context = new NativeKeyedContextFactory()->build(HashAlgorithm::SHA256, 'secret');

        self::assertInstanceOf(HashContextInterface::class, $context);

        $branched = $context->copy();
        $branched->update('hello ');
        $branched->update('world');

        $hash = new class ($branched->finalize(false)) implements HashInterface {
            public function __construct(private readonly string $value)
            {
            }

            public function __toString(): string
            {
                return $this->value;
            }

            public function value(): string
            {
                return $this->value;
            }

            public function algorithm(): string
            {
                return 'sha256';
            }

            public function binary(): bool
            {
                return false;
            }

            public function length(): int
            {
                return strlen($this->value);
            }

            public function equals(HashInterface $other): bool
            {
                return $this->value === $other->value();
            }

            public function toUrn(): string
            {
                return sprintf('urn:hashit:%s:%s', $this->algorithm(), $this->value);
            }
        };

        verify($hash->value())->equals(hash_hmac('sha256', 'hello world', 'secret'));
    }
}
