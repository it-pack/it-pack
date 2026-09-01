<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use ItPack\HashIt\Adapter\NativeFilepathFileinfo;
use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\Contract\HashingObserverInterface;
use ItPack\HashIt\HashObservation;
use ItPack\HashIt\HashObservationKind;
use ItPack\HashIt\HashOperation;
use RuntimeException;

class HashingObserverTest extends Unit
{
    public function testHashEmitsExactlyStartedThenCompleted(): void
    {
        $spy = new SpyObserver();
        $hasher = NativeHashIt::create($spy);

        $hash = $hasher->hash('the quick brown fox', 'sha256');

        self::assertCount(2, $spy->observations);
        self::assertSame(HashObservationKind::STARTED, $spy->observations[0]->kind);
        self::assertSame(HashObservationKind::COMPLETED, $spy->observations[1]->kind);

        $started = $spy->observations[0];
        $completed = $spy->observations[1];
        self::assertSame($started->id, $completed->id);
        self::assertSame(HashOperation::HASH, $started->operation);
        self::assertSame('sha256', $started->algorithm);
        self::assertSame(strlen('the quick brown fox'), $started->bytesTotal);
        self::assertSame(strlen('the quick brown fox'), $completed->bytes);
        self::assertSame($hash->toUrn(), $completed->resultUrn);
        self::assertGreaterThanOrEqual(0, $completed->durationMs);
    }

    public function testStreamHashEmitsProgressBetweenStartedAndCompleted(): void
    {
        $spy = new SpyObserver();
        $hasher = NativeHashIt::create($spy);

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, str_repeat('abcdefgh', 1024)); // 8192 bytes
        rewind($stream);
        $hasher->hashStream($stream, 'sha256', 1024);
        fclose($stream);

        $kinds = array_map(static fn (HashObservation $o) => $o->kind, $spy->observations);
        self::assertSame(HashObservationKind::STARTED, $kinds[0]);
        self::assertSame(HashObservationKind::COMPLETED, end($kinds));

        $progress = array_filter(
            $spy->observations,
            static fn (HashObservation $o) => $o->kind === HashObservationKind::PROGRESS,
        );
        self::assertNotEmpty($progress);
        self::assertGreaterThan(1, count($progress));
        foreach ($progress as $p) {
            self::assertSame($spy->observations[0]->id, $p->id);
            self::assertGreaterThan(0, $p->bytesProcessed);
        }
    }

    public function testFailureEmitsStartedThenFailedCarryingOriginalThrowable(): void
    {
        $spy = new SpyObserver();
        $hasher = NativeHashIt::create($spy);

        $thrown = null;
        try {
            $hasher->hashFile(NativeFilepathFileinfo::of('/this/path/does/not/exist-abc123'), 'sha256');
        } catch (RuntimeException $e) {
            $thrown = $e;
        }

        self::assertInstanceOf(RuntimeException::class, $thrown);
        self::assertCount(2, $spy->observations);
        self::assertSame(HashObservationKind::STARTED, $spy->observations[0]->kind);
        self::assertSame(HashObservationKind::FAILED, $spy->observations[1]->kind);

        $started = $spy->observations[0];
        $failed = $spy->observations[1];
        self::assertSame($started->id, $failed->id);
        self::assertSame(HashOperation::HASH_FILE, $failed->operation);
        self::assertSame($thrown, $failed->error);
    }

    public function testNoObserverKeepsDefaultBehaviour(): void
    {
        // BC: with no observer injected, hashing works exactly as before.
        $hash = NativeHashIt::create()->hash('plain', 'sha256');

        self::assertSame('sha256', $hash->algorithm());
        self::assertSame(hash('sha256', 'plain'), $hash->value());
    }
}

final class SpyObserver implements HashingObserverInterface
{
    /** @var list<HashObservation> */
    public array $observations = [];

    public function observe(HashObservation $observation): void
    {
        $this->observations[] = $observation;
    }
}
