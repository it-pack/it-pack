<?php

declare(strict_types=1);

namespace ItPack\HashIt;

use InvalidArgumentException;
use RuntimeException;

final readonly class KeyedHasher implements KeyedHasherInterface
{
    public function __construct(
        private KeyedContextFactoryInterface $factory,
    ) {
    }

    public function hmac(string $data, string $key, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): HashInterface
    {
        return $this->hashString($data, $key, $algorithm, false);
    }

    public function hmacBinary(string $data, string $key, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): HashInterface
    {
        return $this->hashString($data, $key, $algorithm, true);
    }

    public function hmacFile(FilepathInterface $path, string $key, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): HashInterface
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
            return $this->hmacStream($handle, $key, $algorithm);
        } finally {
            fclose($handle);
        }
    }

    public function verify(string $data, string $key, HashInterface $expected, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): bool
    {
        return $this->hmac($data, $key, $algorithm)->equals($expected);
    }

    public function verifyBinary(string $data, string $key, HashInterface $expected, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): bool
    {
        return $this->hmacBinary($data, $key, $algorithm)->equals($expected);
    }

    public function hmacStream($stream, string $key, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256, int $chunkSize = HasherInterface::DEFAULT_CHUNK_SIZE_BYTES): HashInterface
    {
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new InvalidArgumentException('hmacStream() expects a stream resource');
        }

        if ($chunkSize < 1) {
            throw new InvalidArgumentException('Chunk size must be at least 1 byte');
        }

        $builder = HashBuilder::fromContext($this->factory->build($algorithm, $key), $algorithm);
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

    public function hmacFiles(iterable $paths, string $key, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): array
    {
        $result = [];

        foreach ($paths as $path) {
            $result[(string) $path] = $this->hmacFile($path, $key, $algorithm);
        }

        return $result;
    }

    private function hashString(string $data, string $key, string|HashAlgorithm $algorithm, bool $binary): HashInterface
    {
        $algorithm = $algorithm instanceof HashAlgorithm ? $algorithm->value : $algorithm;
        $context = $this->factory->build($algorithm, $key);
        $context->update($data);

        return new Hash($context->finalize($binary), $algorithm, $binary);
    }

    private function unreadable(string $file): RuntimeException
    {
        return new RuntimeException('Unable to read file: ' . $file);
    }
}
