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

    public function hashFiles(iterable $paths, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): array
    {
        $result = [];

        foreach ($paths as $path) {
            $result[(string) $path] = $this->hashFile($path, $algorithm);
        }

        return $result;
    }

    public function hashDirectory(string $directory, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): array
    {
        if (!is_dir($directory)) {
            throw new RuntimeException('Not a directory: ' . $directory);
        }

        return $this->hashFiles($this->listing->regularFiles($directory), $algorithm);
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
