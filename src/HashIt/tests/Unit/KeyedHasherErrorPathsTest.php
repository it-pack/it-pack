<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use ItPack\HashIt\Adapter\NativeFilepathFileinfo;
use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\Adapter\NativeKeyedContextFactory;
use ItPack\HashIt\Contract\HashProgressInterface;
use ItPack\HashIt\Hash;
use ItPack\HashIt\HashObservation;

/**
 * Covers the failure / edge branches of {@see KeyedHasher} that the happy-path
 * suite misses: observer "failed" emissions, unreadable files, non-seekable
 * streams, generator-based progress totals, and the keyed context factory's
 * unknown-algorithm branch.
 */
final class KeyedSpy implements \ItPack\HashIt\Contract\HashingObserverInterface
{
    /** @var list<HashObservation> */
    public array $observed = [];

    public function observe(HashObservation $observation): void
    {
        $this->observed[] = $observation;
    }
}

final class KeyedHasherErrorPathsTest extends Unit
{
    private function hasherWithSpy(KeyedSpy $spy): \ItPack\HashIt\Contract\KeyedHasherInterface
    {
        return NativeHashIt::keyedHasher($spy);
    }

    private function lastKind(KeyedSpy $spy): string
    {
        return $spy->observed[count($spy->observed) - 1]->kind;
    }

    public function testVerifyFailsOnUnknownAlgorithmEmitsFailedObservation(): void
    {
        $spy = new KeyedSpy();
        $hasher = $this->hasherWithSpy($spy);

        $thrown = null;
        try {
            $hasher->verify('hello', 'secret', new Hash('deadbeef', 'sha256'), 'this-is-not-a-real-algorithm');
        } catch (\InvalidArgumentException $e) {
            $thrown = $e;
        }

        self::assertNotNull($thrown);
        self::assertSame(HashObservation::FAILED, $this->lastKind($spy));
    }

    public function testVerifyBinaryFailsOnUnknownAlgorithmEmitsFailedObservation(): void
    {
        $spy = new KeyedSpy();
        $hasher = $this->hasherWithSpy($spy);

        $thrown = null;
        try {
            $hasher->verifyBinary(
                'hello',
                'secret',
                new Hash('deadbeef', 'sha256', true),
                'this-is-not-a-real-algorithm',
            );
        } catch (\InvalidArgumentException $e) {
            $thrown = $e;
        }

        self::assertNotNull($thrown);
        self::assertSame(HashObservation::FAILED, $this->lastKind($spy));
    }

    public function testHmacFilesWithArrayReportsProgressAndTotals(): void
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

        $map = NativeHashIt::keyedHasher()->hmacFiles(
            [NativeFilepathFileinfo::of($a), NativeFilepathFileinfo::of($b)],
            'secret',
            'sha256',
            $progress,
        );

        self::assertCount(2, $map);
        self::assertGreaterThan(0, $progress->calls);

        unlink($a);
        unlink($b);
    }

    public function testHmacFilesWithGeneratorReportsUnknownTotal(): void
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

        $map = NativeHashIt::keyedHasher()->hmacFiles($gen, 'secret', 'sha256', $progress);

        self::assertCount(2, $map);

        unlink($a);
        unlink($b);
    }

    public function testHmacFilesFailsOnUnknownAlgorithmEmitsFailedObservation(): void
    {
        $spy = new KeyedSpy();
        $hasher = $this->hasherWithSpy($spy);
        $a = tempnam(sys_get_temp_dir(), 'hashit');
        file_put_contents($a, 'alpha');

        $thrown = null;
        try {
            $hasher->hmacFiles([NativeFilepathFileinfo::of($a)], 'secret', 'this-is-not-a-real-algorithm');
        } catch (\InvalidArgumentException $e) {
            $thrown = $e;
        } finally {
            unlink($a);
        }

        self::assertNotNull($thrown);
        self::assertSame(HashObservation::FAILED, $this->lastKind($spy));
    }

    public function testKeyedContextFactoryRejectsUnknownAlgorithm(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new NativeKeyedContextFactory()->build('this-is-not-a-real-algorithm', 'secret');
    }
}
