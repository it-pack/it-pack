<?php

declare(strict_types=1);

namespace ItPack\HashIt;

use InvalidArgumentException;
use RuntimeException;

interface HasherInterface
{
    public const int DEFAULT_CHUNK_SIZE_BYTES = 65536;

    public function hash(string $data, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): HashInterface;

    public function hashBinary(string $data, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): HashInterface;

    /**
     * @throws RuntimeException when the file cannot be read
     * @throws InvalidArgumentException when the algorithm is unknown
     */
    public function hashFile(FilepathInterface $path, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): HashInterface;

    public function verify(string $data, HashInterface $expected, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): bool;

    public function verifyBinary(string $data, HashInterface $expected, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): bool;

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
    public function hashStream($stream, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256, int $chunkSize = self::DEFAULT_CHUNK_SIZE_BYTES, ?HashProgressInterface $progress = null): HashInterface;

    /**
     * @param iterable<FilepathInterface> $paths
     * @param HashProgressInterface|null $progress optional byte-level progress sink (fires after each file)
     * @return array<string, HashInterface>
     */
    public function hashFiles(iterable $paths, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256, ?HashProgressInterface $progress = null): array;

    /**
     * @param HashProgressInterface|null $progress optional byte-level progress sink (fires after each file)
     * @return array<string, HashInterface>
     */
    public function hashDirectory(string $directory, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256, ?HashProgressInterface $progress = null): array;

    /**
     * The algorithm catalogue this hasher was composed with (extensible via a
     * custom {@see \ItPack\HashIt\AlgorithmSourceInterface}).
     *
     * @return list<HashAlgorithm>
     */
    public function algorithms(): array;

    public function supports(string $identifier): bool;

    public function resolve(string $identifier): HashAlgorithm;

    /**
     * Deterministically hash *structured* content built from the allowed input
     * kinds (string, stream, file) composed in a fixed order via {@see HashInputInterface}.
     * The same logical content always digests identically regardless of how it was
     * assembled; see {@see \ItPack\HashIt\HashInput\CompositeInput} for the framing.
     */
    public function hashInput(HashInputInterface $input, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): HashInterface;
}
