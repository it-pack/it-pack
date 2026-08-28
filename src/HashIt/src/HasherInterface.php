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
     *
     * @throws InvalidArgumentException when $stream is not a stream resource or $chunkSize is not positive
     */
    public function hashStream($stream, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256, int $chunkSize = self::DEFAULT_CHUNK_SIZE_BYTES): HashInterface;

    /**
     * @param iterable<FilepathInterface> $paths
     * @return array<string, HashInterface>
     */
    public function hashFiles(iterable $paths, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): array;

    /**
     * @return array<string, HashInterface>
     */
    public function hashDirectory(string $directory, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): array;
}
