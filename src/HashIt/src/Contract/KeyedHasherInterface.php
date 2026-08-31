<?php

declare(strict_types=1);

namespace ItPack\HashIt\Contract;

use InvalidArgumentException;
use ItPack\HashIt\HashAlgorithm;
use RuntimeException;

/**
 * Keyed (HMAC) hashing surface, mirroring the plain {@see HasherInterface} so the
 * two paths offer symmetric capabilities: one-shot, binary, file, streaming,
 * bulk and timing-safe verification.
 *
 * The minimal one-shot {@see HmacHasherInterface} stays intentionally tiny; this
 * interface is the dedicated port for the derived/streaming/bulk operations that
 * the plain path exposes but the original HMAC path lacked (review finding F-8).
 */
interface KeyedHasherInterface
{
    public function hmac(string $data, string $key, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): HashInterface;

    public function hmacBinary(string $data, string $key, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): HashInterface;

    /**
     * @throws RuntimeException when the file cannot be read
     * @throws InvalidArgumentException when the algorithm is unknown or not HMAC-safe
     */
    public function hmacFile(FilepathInterface $path, string $key, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): HashInterface;

    public function verify(string $data, string $key, HashInterface $expected, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): bool;

    public function verifyBinary(string $data, string $key, HashInterface $expected, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): bool;

    /**
     * Hash a readable stream in fixed chunks without loading it fully into memory.
     *
     * Seekable streams are rewound to position 0 before reading and restored to
     * their prior position afterwards; non-seekable streams are hashed from the
     * current position and left where reading stopped.
     *
     * @param resource $stream
     * @param HashProgressInterface|null $progress optional byte-level progress sink (fires after each chunk)
     *
     * @throws InvalidArgumentException when $stream is not a stream resource or $chunkSize is not positive
     */
    public function hmacStream($stream, string $key, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256, int $chunkSize = HasherInterface::DEFAULT_CHUNK_SIZE_BYTES, ?HashProgressInterface $progress = null): HashInterface;

    /**
     * @param iterable<FilepathInterface> $paths
     * @param HashProgressInterface|null $progress optional byte-level progress sink (fires after each file)
     * @return array<string, HashInterface>
     */
    public function hmacFiles(iterable $paths, string $key, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256, ?HashProgressInterface $progress = null): array;

    /**
     * The algorithm catalogue this hasher was composed with (extensible via a
     * custom {@see \ItPack\HashIt\Contract\AlgorithmSourceInterface}).
     *
     * @return list<HashAlgorithm>
     */
    public function algorithms(): array;

    public function supports(string $identifier): bool;

    public function resolve(string $identifier): HashAlgorithm;
}
