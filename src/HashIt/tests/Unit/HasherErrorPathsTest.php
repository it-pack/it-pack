<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use ItPack\HashIt\Adapter\NativeFilepathFileinfo;
use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\Contract\HashInputInterface;
use ItPack\HashIt\Contract\HashProgressInterface;
use ItPack\HashIt\Contract\HashSinkInterface;
use ItPack\HashIt\Hash;
use ItPack\HashIt\HashInput\StringInput;
use ItPack\HashIt\HashInput\TempSink;
use ItPack\HashIt\HashObservation;

/**
 * Covers the failure / edge branches of {@see Hasher} that the happy-path suite
 * misses: observer "failed" emissions, unreadable files, non-seekable streams,
 * generator-based progress totals, and a HashInput that returns a non-builder sink.
 */
final class HasherSpy implements \ItPack\HashIt\Contract\HashingObserverInterface
{
    /** @var list<HashObservation> */
    public array $observed = [];

    public function observe(HashObservation $observation): void
    {
        $this->observed[] = $observation;
    }
}

final class HasherErrorPathsTest extends Unit
{
    private function hasherWithSpy(HasherSpy $spy): \ItPack\HashIt\Contract\HasherInterface
    {
        return NativeHashIt::create($spy);
    }

    private function lastKind(HasherSpy $spy): string
    {
        return $spy->observed[count($spy->observed) - 1]->kind;
    }

    public function testHashFailsOnUnknownAlgorithmEmitsFailedObservation(): void
    {
        $spy = new HasherSpy();
        $hasher = $this->hasherWithSpy($spy);

        $thrown = null;
        try {
            $hasher->hash('hello', 'this-is-not-a-real-algorithm');
        } catch (\InvalidArgumentException $e) {
            $thrown = $e;
        }

        self::assertNotNull($thrown);
        self::assertSame(HashObservation::FAILED, $this->lastKind($spy));
    }

    public function testHashBinaryFailsOnUnknownAlgorithmEmitsFailedObservation(): void
    {
        $spy = new HasherSpy();
        $hasher = $this->hasherWithSpy($spy);

        $thrown = null;
        try {
            $hasher->hashBinary('hello', 'this-is-not-a-real-algorithm');
        } catch (\InvalidArgumentException $e) {
            $thrown = $e;
        }

        self::assertNotNull($thrown);
        self::assertSame(HashObservation::FAILED, $this->lastKind($spy));
    }

    public function testVerifyFailsOnUnknownAlgorithmEmitsFailedObservation(): void
    {
        $spy = new HasherSpy();
        $hasher = $this->hasherWithSpy($spy);

        $thrown = null;
        try {
            $hasher->verify('hello', new Hash('deadbeef', 'sha256'), 'this-is-not-a-real-algorithm');
        } catch (\InvalidArgumentException $e) {
            $thrown = $e;
        }

        self::assertNotNull($thrown);
        self::assertSame(HashObservation::FAILED, $this->lastKind($spy));
    }

    public function testHashFilesWithArrayReportsProgressAndTotals(): void
    {
        $a = tempnam(sys_get_temp_dir(), 'hashit');
        $b = tempnam(sys_get_temp_dir(), 'hashit');
        file_put_contents($a, 'alpha');
        file_put_contents($b, 'beta');

        $progress = new class () implements HashProgressInterface {
            public int $calls = 0;

            public function onBytes(int $processed, ?int $total): void
            {
                $this->calls++;
            }
        };

        $map = NativeHashIt::create()->hashFiles(
            [NativeFilepathFileinfo::of($a), NativeFilepathFileinfo::of($b)],
            'sha256',
            $progress,
        );

        self::assertCount(2, $map);
        self::assertGreaterThan(0, $progress->calls);

        unlink($a);
        unlink($b);
    }

    public function testHashFilesWithGeneratorReportsUnknownTotal(): void
    {
        $a = tempnam(sys_get_temp_dir(), 'hashit');
        $b = tempnam(sys_get_temp_dir(), 'hashit');
        file_put_contents($a, 'alpha');
        file_put_contents($b, 'beta');

        $progress = new class () implements HashProgressInterface {
            public function onBytes(int $processed, ?int $total): void
            {
            }
        };

        $gen = (static function () use ($a, $b): \Generator {
            yield NativeFilepathFileinfo::of($a);
            yield NativeFilepathFileinfo::of($b);
        })();

        $map = NativeHashIt::create()->hashFiles($gen, 'sha256', $progress);

        self::assertCount(2, $map);

        unlink($a);
        unlink($b);
    }

    public function testHashFilesFailsOnUnknownAlgorithmEmitsFailedObservation(): void
    {
        $spy = new HasherSpy();
        $hasher = $this->hasherWithSpy($spy);
        $a = tempnam(sys_get_temp_dir(), 'hashit');
        file_put_contents($a, 'alpha');

        $thrown = null;
        try {
            $hasher->hashFiles([NativeFilepathFileinfo::of($a)], 'this-is-not-a-real-algorithm');
        } catch (\InvalidArgumentException $e) {
            $thrown = $e;
        } finally {
            unlink($a);
        }

        self::assertNotNull($thrown);
        self::assertSame(HashObservation::FAILED, $this->lastKind($spy));
    }

    public function testHashDirectoryFailsOnUnknownAlgorithmEmitsFailedObservation(): void
    {
        $spy = new HasherSpy();
        $hasher = $this->hasherWithSpy($spy);
        $dir = sys_get_temp_dir() . '/hashit_dir_' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/one.txt', 'one');

        $thrown = null;
        try {
            $hasher->hashDirectory($dir, 'this-is-not-a-real-algorithm');
        } catch (\InvalidArgumentException $e) {
            $thrown = $e;
        } finally {
            unlink($dir . '/one.txt');
            rmdir($dir);
        }

        self::assertNotNull($thrown);
        self::assertSame(HashObservation::FAILED, $this->lastKind($spy));
    }

    public function testHashInputFailsOnUnknownAlgorithmEmitsFailedObservation(): void
    {
        $spy = new HasherSpy();
        $hasher = $this->hasherWithSpy($spy);

        $thrown = null;
        try {
            $hasher->hashInput(new StringInput('hello'), 'this-is-not-a-real-algorithm');
        } catch (\InvalidArgumentException $e) {
            $thrown = $e;
        }

        self::assertNotNull($thrown);
        self::assertSame(HashObservation::FAILED, $this->lastKind($spy));
    }

    public function testHashInputRejectsSinkThatIsNotHashBuilder(): void
    {
        $input = new class () implements HashInputInterface {
            public function writeTo(HashSinkInterface $sink): HashSinkInterface
            {
                return new TempSink();
            }
        };

        $this->expectException(\RuntimeException::class);
        NativeHashIt::create()->hashInput($input);
    }
}
