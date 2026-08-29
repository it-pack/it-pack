<?php

declare(strict_types=1);

namespace ItPack\HashIt;

use InvalidArgumentException;
use RuntimeException;

final readonly class Hasher implements HasherInterface
{
    public function __construct(
        private ContextFactoryInterface $factory,
        private DirectoryListingInterface $listing,
    ) {
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

        if (!is_file($file)) {
            throw $this->unreadable($file);
        }

        $handle = fopen($file, 'rb');
        if ($handle === false) {
            throw $this->unreadable($file);
        }

        try {
            return $this->hashStream($handle, $algorithm);
        } finally {
            fclose($handle);
        }
    }

    public function verify(string $data, HashInterface $expected, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): bool
    {
        return $this->hash($data, $algorithm)->equals($expected);
    }

    public function verifyBinary(string $data, HashInterface $expected, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): bool
    {
        return $this->hashBinary($data, $algorithm)->equals($expected);
    }

    public function hashStream($stream, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256, int $chunkSize = self::DEFAULT_CHUNK_SIZE_BYTES, ?HashProgressInterface $progress = null): HashInterface
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

        $total = $this->streamTotalBytes($stream);
        $processed = 0;

        while (!feof($stream)) {
            $chunk = fread($stream, $chunkSize);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $builder = $builder->append($chunk);
            $processed += strlen($chunk);
            $progress?->onBytes($processed, $total);
        }

        fseek($stream, $position);

        return $builder->digest();
    }

    public function hashFiles(iterable $paths, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256, ?HashProgressInterface $progress = null): array
    {
        $result = [];

        $total = $progress === null ? null : $this->iterableTotalBytes($paths);
        $processed = 0;

        foreach ($paths as $path) {
            $result[(string) $path] = $this->hashFile($path, $algorithm);

            $size = filesize((string) $path);
            $processed += $size === false ? 0 : $size;
            $progress?->onBytes($processed, $total);
        }

        return $result;
    }

    public function hashDirectory(string $directory, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256, ?HashProgressInterface $progress = null): array
    {
        if (!is_dir($directory)) {
            throw new RuntimeException('Not a directory: ' . $directory);
        }

        return $this->hashFiles($this->listing->regularFiles($directory), $algorithm, $progress);
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

    /**
     * Total bytes of a stream when it is seekable (so its size is known), else null.
     *
     * @param resource $stream
     */
    private function streamTotalBytes($stream): ?int
    {
        $meta = stream_get_meta_data($stream);
        if (!$meta['seekable']) {
            return null;
        }

        $stat = fstat($stream);
        if ($stat === false) {
            return null;
        }

        return $stat['size'] >= 0 ? $stat['size'] : null;
    }

    /**
     * Sum of file sizes across an iterable of paths, when the iterable is an array
     * (so it can be walked twice); null for one-shot traversables whose total is
     * not known upfront.
     *
     * @param iterable<FilepathInterface> $paths
     */
    private function iterableTotalBytes(iterable $paths): ?int
    {
        if (!is_array($paths)) {
            return null;
        }

        $total = 0;
        foreach ($paths as $path) {
            $size = filesize((string) $path);
            if ($size !== false) {
                $total += $size;
            }
        }

        return $total;
    }
}
