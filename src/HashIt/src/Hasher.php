<?php

declare(strict_types=1);

namespace ItPack\HashIt;

use DirectoryIterator;
use InvalidArgumentException;
use ItPack\HashIt\Adapter\NativeFilepathFileinfo;
use RuntimeException;

final readonly class Hasher
{
    private const int DEFAULT_CHUNK_SIZE_BYTES = 65536;

    public function __construct(private ContextFactoryInterface $factory)
    {
    }

    public function hash(string $data, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): HashInterface
    {
        return $this->hashString($data, $algorithm, false);
    }

    public function hashBinary(string $data, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): HashInterface
    {
        return $this->hashString($data, $algorithm, true);
    }

    public function hashFile(FilepathInterface $path, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): HashInterface
    {
        $file = (string) $path;

        if (!is_file($file) || !is_readable($file)) {
            throw $this->unreadable($file);
        }

        $contents = file_get_contents($file);
        if ($contents === false) {
            throw $this->unreadable($file);
        }

        return $this->hash($contents, $algorithm);
    }

    public function verify(string $data, HashInterface $expected, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): bool
    {
        return $this->hash($data, $algorithm)->equals($expected);
    }

    public function verifyBinary(string $data, HashInterface $expected, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): bool
    {
        return $this->hashBinary($data, $algorithm)->equals($expected);
    }

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
    public function hashStream($stream, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256, int $chunkSize = self::DEFAULT_CHUNK_SIZE_BYTES): HashInterface
    {
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new InvalidArgumentException('hashStream() expects a stream resource');
        }

        if ($chunkSize < 1) {
            throw new InvalidArgumentException('Chunk size must be at least 1 byte');
        }

        $builder = HashBuilder::start($this->factory, $algorithm);
        $position = ftell($stream);
        if ($position === false) {
            $position = 0;
        }
        fseek($stream, 0);

        while (!feof($stream)) {
            $chunk = fread($stream, $chunkSize);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $builder = $builder->append($chunk);
        }

        fseek($stream, $position);

        return $builder->digest();
    }

    /**
     * Hash many files at once, returning a path => Hash map.
     *
     * @param iterable<FilepathInterface> $paths
     * @return array<string, HashInterface>
     */
    public function hashFiles(iterable $paths, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): array
    {
        $result = [];

        foreach ($paths as $path) {
            $result[(string) $path] = $this->hashFile($path, $algorithm);
        }

        return $result;
    }

    /**
     * Hash every regular file directly inside a directory (non-recursive).
     *
     * @return array<string, HashInterface>
     */
    public function hashDirectory(string $directory, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): array
    {
        if (!is_dir($directory)) {
            throw new RuntimeException('Not a directory: ' . $directory);
        }

        $paths = [];
        foreach (new DirectoryIterator($directory) as $item) {
            if ($item->isFile()) {
                $paths[] = new NativeFilepathFileinfo($item->getFileInfo());
            }
        }

        return $this->hashFiles($paths, $algorithm);
    }

    private function hashString(string $data, string|HashAlgorithm $algorithm, bool $binary): HashInterface
    {
        $algorithm = $algorithm instanceof HashAlgorithm ? $algorithm->value : $algorithm;
        $context = $this->factory->build($algorithm);
        $context->update($data);

        return new Hash($context->finalize($binary), $algorithm, $binary);
    }

    private function unreadable(string $file): RuntimeException
    {
        return new RuntimeException('Unable to read file: ' . $file);
    }
}
