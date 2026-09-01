<?php

declare(strict_types=1);

namespace ItPack\HashIt\Test\Unit;

use ItPack\HashIt\Adapter\NativeFilepathFileinfo;
use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\Contract\HasherInterface;
use ItPack\HashIt\Contract\HashInputInterface;
use ItPack\HashIt\Factory\TempSinkFactory;
use ItPack\HashIt\HashAlgorithm;
use ItPack\HashIt\HashInput\CompositeInput;
use ItPack\HashIt\HashInput\FileInput;
use ItPack\HashIt\HashInput\StreamInput;
use ItPack\HashIt\HashInput\StringInput;
use ItPack\HashIt\HashInput\TempSink;
use PHPUnit\Framework\TestCase;

use function verify;

final class HashInputTest extends TestCase
{
    private TempSinkFactory $sinks;

    protected function setUp(): void
    {
        $this->sinks = new TempSinkFactory();
    }

    private function hasher(): HasherInterface
    {
        return NativeHashIt::create();
    }

    /**
     * @param list<HashInputInterface> $children
     */
    private function composite(array $children): CompositeInput
    {
        return new CompositeInput($children, $this->sinks);
    }

    public function testSingleStringInputEqualsPlainHash(): void
    {
        $hash = $this->hasher()->hashInput(new StringInput('x'));

        verify($hash->value())->equals($this->hasher()->hash('x')->value());
    }

    public function testStreamInputOverFileEqualsHashFile(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'hashit');
        file_put_contents($tmp, 'the quick brown fox');

        $stream = fopen($tmp, 'rb');
        self::assertIsResource($stream);

        try {
            $hash = $this->hasher()->hashInput(new StreamInput($stream));
        } finally {
            fclose($stream);
        }

        verify($hash->value())->equals(
            $this->hasher()->hashFile(NativeFilepathFileinfo::of($tmp))->value(),
        );

        unlink($tmp);
    }

    public function testFileInputEqualsHashFile(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'hashit');
        file_put_contents($tmp, 'structured content');

        $hash = $this->hasher()->hashInput(new FileInput(NativeFilepathFileinfo::of($tmp)));

        verify($hash->value())->equals(
            $this->hasher()->hashFile(NativeFilepathFileinfo::of($tmp))->value(),
        );

        unlink($tmp);
    }

    public function testEquivalentCompositesDigestIdenticallyRegardlessOfAssembly(): void
    {
        $sequence = [
            new StringInput('header'),
            new StringInput('body'),
            new StringInput('footer'),
        ];

        // built directly from the ordered list
        $built = $this->composite($sequence);

        // assembled in a different code path (map + concatenate) but identical order
        $mapped = array_map(static fn (HashInputInterface $i): HashInputInterface => $i, $sequence);
        $assembled = $this->composite([
            ...array_slice($mapped, 0, 1),
            ...array_slice($mapped, 1),
        ]);

        $hasher = $this->hasher();
        verify($hasher->hashInput($built)->value())
            ->equals($hasher->hashInput($assembled)->value())
        ;
    }

    public function testDifferentOrderDigestsDifferently(): void
    {
        $forward = $this->composite([
            new StringInput('a'),
            new StringInput('b'),
        ]);

        $reverse = $this->composite([
            new StringInput('b'),
            new StringInput('a'),
        ]);

        $hasher = $this->hasher();
        verify($hasher->hashInput($forward)->value())
            ->notEquals($hasher->hashInput($reverse)->value())
        ;
    }

    public function testFramedCompositeDiffersFromConcatenatedPlainHash(): void
    {
        // A composite frames each child with an 8-byte length prefix, so it must
        // NOT equal hashing the bare concatenation of the bytes.
        $composite = $this->composite([
            new StringInput('ab'),
            new StringInput('cd'),
        ]);

        $compositeHash = $this->hasher()->hashInput($composite)->value();
        $bareConcat = $this->hasher()->hash('abcd')->value();

        verify($compositeHash)->notEquals($bareConcat);
    }

    public function testNestedCompositeIsCanonical(): void
    {
        $nested = $this->composite([
            new StringInput('x'),
            $this->composite([
                new StringInput('y'),
                new StringInput('z'),
            ]),
        ]);

        $again = $this->composite([
            new StringInput('x'),
            $this->composite([
                new StringInput('y'),
                new StringInput('z'),
            ]),
        ]);

        $hasher = $this->hasher();
        verify($hasher->hashInput($nested)->value())
            ->equals($hasher->hashInput($again)->value())
        ;
    }

    public function testAlgorithmIsHonoured(): void
    {
        $hash = $this->hasher()->hashInput(new StringInput('x'), HashAlgorithm::SHA512);

        verify($hash->algorithm())->equals('sha512');
        verify($hash->value())->equals(hash('sha512', 'x'));
    }

    public function testStreamInputRejectsNonResource(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->hasher()->hashInput(new StreamInput('not a resource'));
    }

    public function testStreamInputRejectsNonPositiveChunkSize(): void
    {
        $stream = fopen('php://memory', 'rb');

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->hasher()->hashInput(new StreamInput($stream, 0));
        } finally {
            fclose($stream);
        }
    }

    public function testFileInputThrowsOnMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->hasher()->hashInput(new FileInput(NativeFilepathFileinfo::of('/nonexistent/path/xyz')));
    }

    public function testTempSinkRejectsNonPositiveChunkSize(): void
    {
        $sink = new TempSink();

        // chunks() is a generator: calling it returns a Generator without
        // running the body. Iterating drives the body and hits the size guard.
        $this->expectException(\InvalidArgumentException::class);
        iterator_to_array($sink->chunks(0));
    }
}
