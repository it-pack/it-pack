<?php

declare(strict_types=1);

namespace ItPack\HashIt\Tests\Unit;

use InvalidArgumentException;
use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\HashAlgorithm;
use ItPack\HashIt\ResumableHashingInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\HashIt\ResumableHasher
 * @covers \ItPack\HashIt\ResumableHash
 * @covers \ItPack\HashIt\ResumableHashingInterface
 */
final class ResumableHasherTest extends TestCase
{
    private ResumableHashingInterface $resumable;

    protected function setUp(): void
    {
        $this->resumable = NativeHashIt::resumable();
    }

    public function testResumeAcrossHandlesMatchesPlainHash(): void
    {
        $data = 'the quick brown fox jumps over the lazy dog';

        $handle = $this->resumable->start(HashAlgorithm::SHA256);
        $handle->update(substr($data, 0, 20));
        $checkpoint = $handle->checkpoint();

        // Resume in a fresh handle (simulates a new request / process).
        $resumed = $this->resumable->resume($checkpoint);
        $resumed->update(substr($data, 20));

        $hash = $resumed->finalize();
        $expected = NativeHashIt::create()->hash($data, HashAlgorithm::SHA256);

        self::assertTrue($hash->equals($expected));
    }

    public function testMultipleCheckpointsAndResumes(): void
    {
        $parts = ['alpha', 'bravo', 'charlie', 'delta'];
        $data = implode('', $parts);

        $handle = $this->resumable->start(HashAlgorithm::SHA256);
        $checkpoint = null;
        foreach ($parts as $part) {
            $handle->update($part);
            $checkpoint = $handle->checkpoint();
        }

        $resumed = $this->resumable->resume($checkpoint);
        $hash = $resumed->finalize();

        self::assertTrue($hash->equals(NativeHashIt::create()->hash($data, HashAlgorithm::SHA256)));
    }

    public function testCheckpointDoesNotConsumeLiveHandle(): void
    {
        $handle = $this->resumable->start(HashAlgorithm::SHA256);
        $handle->update('hello');
        $handle->checkpoint();

        // The same handle can keep going after a checkpoint.
        $handle->update(' world');
        $hash = $handle->finalize();

        self::assertTrue($hash->equals(NativeHashIt::create()->hash('hello world', HashAlgorithm::SHA256)));
    }

    public function testBinaryFlagIsCarriedByCheckpoint(): void
    {
        $handle = $this->resumable->start(HashAlgorithm::SHA256, true);
        $handle->update('hello world');
        $checkpoint = $handle->checkpoint();

        $resumed = $this->resumable->resume($checkpoint);
        $hash = $resumed->finalize();

        self::assertTrue($hash->binary());
        self::assertSame(
            NativeHashIt::create()->hashBinary('hello world', HashAlgorithm::SHA256)->value(),
            $hash->value(),
        );
    }

    public function testWorksForOtherAlgorithms(): void
    {
        $data = 'resumable sha3';
        $handle = $this->resumable->start(HashAlgorithm::SHA3_256);
        $handle->update($data);
        $checkpoint = $handle->checkpoint();

        $hash = $this->resumable->resume($checkpoint)->finalize();

        self::assertTrue($hash->equals(NativeHashIt::create()->hash($data, HashAlgorithm::SHA3_256)));
    }

    public function testInvalidCheckpointRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->resumable->resume('not-a-checkpoint');
    }

    public function testTamperedCheckpointRejected(): void
    {
        $handle = $this->resumable->start(HashAlgorithm::SHA256);
        $handle->update('hello');
        $checkpoint = $handle->checkpoint();

        // Corrupt the inner serialized context so restore() rejects it.
        $envelope = unserialize($checkpoint);
        $envelope['state'] = 'C:10:"HashContext":5:{}';
        $tampered = serialize($envelope);

        $this->expectException(InvalidArgumentException::class);

        $this->resumable->resume($tampered)->finalize();
    }
}
