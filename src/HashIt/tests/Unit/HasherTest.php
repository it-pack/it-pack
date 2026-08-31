<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use ItPack\HashIt\Adapter\NativeFilepathFileinfo;
use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\Contract\ContextFactoryInterface;
use ItPack\HashIt\Contract\DirectoryListingInterface;
use ItPack\HashIt\Contract\FilepathInterface;
use ItPack\HashIt\Contract\HashContextInterface;
use ItPack\HashIt\Contract\HasherInterface;
use ItPack\HashIt\Contract\HmacHasherInterface;
use ItPack\HashIt\HashAlgorithm;
use ItPack\HashIt\Hasher;

class HasherTest extends Unit
{
    private function hasher(): HasherInterface
    {
        return NativeHashIt::create();
    }

    public function testCompositionRootReturnsPortBackedServices(): void
    {
        self::assertInstanceOf(HasherInterface::class, NativeHashIt::create());
        self::assertInstanceOf(HmacHasherInterface::class, NativeHashIt::hmacHasher());
    }

    public function testHashString(): void
    {
        $hash = $this->hasher()->hash('abc');

        verify($hash->value())->equals(hash('sha256', 'abc'));
        verify($hash->algorithm())->equals('sha256');
        verify($hash->binary())->false();
        verify($hash->length())->equals(64);
    }

    public function testHashWithEnumAlgorithm(): void
    {
        $hash = $this->hasher()->hash('abc', HashAlgorithm::SHA512);

        verify($hash->algorithm())->equals('sha512');
        verify($hash->value())->equals(hash('sha512', 'abc'));
    }

    public function testHashBinary(): void
    {
        $hash = $this->hasher()->hashBinary('abc');

        verify($hash->binary())->true();
        verify($hash->value())->equals(hash('sha256', 'abc', true));
    }

    public function testHashFile(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'hashit');
        file_put_contents($tmp, 'hello world');

        $hash = $this->hasher()->hashFile(
            new NativeFilepathFileinfo(new \SplFileInfo($tmp)),
            'sha256',
        );

        verify($hash->value())->equals(hash('sha256', 'hello world'));

        unlink($tmp);
    }

    public function testHashFileThrowsOnMissing(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->hasher()->hashFile(
            new NativeFilepathFileinfo(new \SplFileInfo('/nonexistent/path/xyz')),
        );
    }

    public function testHashFileStreamsLargePayloadInChunks(): void
    {
        $payload = str_repeat('abcdefgh', 20000); // ~160 KiB, spans 3 chunks at 64 KiB
        $tmp = tempnam(sys_get_temp_dir(), 'hashit');
        file_put_contents($tmp, $payload);

        $hash = $this->hasher()->hashFile(NativeFilepathFileinfo::of($tmp));

        unlink($tmp);

        verify($hash->value())->equals(hash('sha256', $payload));
    }

    public function testVerifyMatchesExpectedDigest(): void
    {
        $hasher = $this->hasher();
        $expected = $hasher->hash('abc');

        verify($hasher->verify('abc', $expected))->true();
    }

    public function testVerifyRejectsWrongData(): void
    {
        $hasher = $this->hasher();
        $expected = $hasher->hash('abc');

        verify($hasher->verify('abd', $expected))->false();
    }

    public function testVerifyBinaryMatchesExpectedDigest(): void
    {
        $hasher = $this->hasher();
        $expected = $hasher->hashBinary('abc');

        verify($hasher->verifyBinary('abc', $expected))->true();
    }

    public function testHashStreamMatchesHashFile(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'hashit');
        file_put_contents($tmp, 'hello world streamed');

        $hasher = $this->hasher();
        $stream = fopen($tmp, 'rb');

        $streamed = $hasher->hashStream($stream);
        fclose($stream);

        verify($streamed->value())->equals(hash('sha256', 'hello world streamed'));

        unlink($tmp);
    }

    public function testHashStreamHandlesLargePayloadInChunks(): void
    {
        $payload = str_repeat('abcdefgh', 20000); // ~160 KiB, spans 3 chunks at 64 KiB
        $stream = fopen('php://memory', 'rb+');
        fwrite($stream, $payload);
        rewind($stream);

        $hash = $this->hasher()->hashStream($stream, 'sha256', 65536);
        fclose($stream);

        verify($hash->value())->equals(hash('sha256', $payload));
    }

    public function testHashStreamRejectsNonResource(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $closed = tmpfile();
        fclose($closed);
        $this->hasher()->hashStream($closed);
    }

    public function testHashStreamRejectsNonPositiveChunkSize(): void
    {
        $stream = fopen('php://memory', 'rb');

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->hasher()->hashStream($stream, 'sha256', 0);
        } finally {
            fclose($stream);
        }
    }

    public function testHashFilesReturnsPathMap(): void
    {
        $a = tempnam(sys_get_temp_dir(), 'hashit');
        $b = tempnam(sys_get_temp_dir(), 'hashit');
        file_put_contents($a, 'file a');
        file_put_contents($b, 'file b');

        $hasher = $this->hasher();
        $map = $hasher->hashFiles([
            NativeFilepathFileinfo::of($a),
            NativeFilepathFileinfo::of($b),
        ]);

        verify(count($map))->equals(2);
        verify($map[$a]->value())->equals(hash('sha256', 'file a'));
        verify($map[$b]->value())->equals(hash('sha256', 'file b'));

        unlink($a);
        unlink($b);
    }

    public function testHashDirectoryHashesRegularFilesOnly(): void
    {
        $dir = sys_get_temp_dir() . '/hashit_dir_' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/one.txt', 'one');
        file_put_contents($dir . '/two.txt', 'two');
        mkdir($dir . '/sub');

        $map = $this->hasher()->hashDirectory($dir);

        verify(count($map))->equals(2);
        verify($map[$dir . '/one.txt']->value())->equals(hash('sha256', 'one'));
        verify($map[$dir . '/two.txt']->value())->equals(hash('sha256', 'two'));

        $files = glob($dir . '/*.*');
        if ($files === false) {
            $files = [];
        }
        array_map('unlink', $files);
        rmdir($dir . '/sub');
        rmdir($dir);
    }

    public function testHashDirectoryRejectsNonDirectory(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->hasher()->hashDirectory('/nonexistent/path/xyz');
    }

    public function testHashDirectoryOrderIsDeterministic(): void
    {
        $dir = sys_get_temp_dir() . '/hashit_dir_' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/c.txt', 'c');
        file_put_contents($dir . '/a.txt', 'a');
        file_put_contents($dir . '/b.txt', 'b');

        $first = array_keys($this->hasher()->hashDirectory($dir));
        $second = array_keys($this->hasher()->hashDirectory($dir));

        $files = glob($dir . '/*.txt');
        if ($files === false) {
            $files = [];
        }
        array_map('unlink', $files);
        rmdir($dir);

        verify($second)->equals($first);
        verify($first)->equals([$dir . '/a.txt', $dir . '/b.txt', $dir . '/c.txt']);
    }

    public function testHashDirectoryDelegatesToInjectedPorts(): void
    {
        $dir = sys_get_temp_dir() . '/hashit_fake_' . uniqid();
        mkdir($dir);
        $a = tempnam(sys_get_temp_dir(), 'hashit');
        $b = tempnam(sys_get_temp_dir(), 'hashit');

        // The scanned directory is empty: both entries come from the injected
        // listing port, and digests come from the injected context factory.
        $hasher = new Hasher(self::fakeFactory(), self::fakeListing([
            self::fakePath($a),
            self::fakePath($b),
        ]));
        self::assertInstanceOf(HasherInterface::class, $hasher);

        $map = $hasher->hashDirectory($dir);

        rmdir($dir);
        unlink($a);
        unlink($b);

        verify(count($map))->equals(2);
        verify($map[$a]->value())->equals('fakedigest');
        verify($map[$b]->value())->equals('fakedigest');
    }

    private static function fakePath(string $path): FilepathInterface
    {
        return new class ($path) implements FilepathInterface {
            public function __construct(private readonly string $path)
            {
            }

            public function __toString(): string
            {
                return $this->path;
            }
        };
    }

    private static function fakeFactory(): ContextFactoryInterface
    {
        return new class () implements ContextFactoryInterface {
            public function build(string $algorithm): HashContextInterface
            {
                return self::fakeContext();
            }

            public function restore(string $checkpoint): HashContextInterface
            {
                return self::fakeContext();
            }

            private static function fakeContext(): HashContextInterface
            {
                return new class () implements HashContextInterface {
                    public function update(string $data): void
                    {
                    }

                    public function finalize(bool $binary): string
                    {
                        return 'fakedigest';
                    }

                    public function copy(): HashContextInterface
                    {
                        return $this;
                    }

                    public function checkpoint(): string
                    {
                        return 'fakecheckpoint';
                    }
                };
            }
        };
    }

    private static function fakeListing(array $paths): DirectoryListingInterface
    {
        return new class ($paths) implements DirectoryListingInterface {
            public function __construct(private readonly array $paths)
            {
            }

            public function regularFiles(string $directory): array
            {
                return $this->paths;
            }
        };
    }
}
