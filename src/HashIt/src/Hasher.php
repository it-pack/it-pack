<?php

declare(strict_types=1);

namespace ItPack\HashIt;

final readonly class Hasher
{
    public function __construct(private ContextFactoryInterface $factory)
    {
    }

    public function hash(string $data, string|HashAlgorithm $algorithm = 'sha256'): HashInterface
    {
        $algorithm = $algorithm instanceof HashAlgorithm ? $algorithm->value : $algorithm;
        $context = $this->factory->build($algorithm);
        $context->update($data);

        return new Hash($context->finalize(false), $algorithm, false);
    }

    public function hashBinary(string $data, string|HashAlgorithm $algorithm = 'sha256'): HashInterface
    {
        $algorithm = $algorithm instanceof HashAlgorithm ? $algorithm->value : $algorithm;
        $context = $this->factory->build($algorithm);
        $context->update($data);

        return new Hash($context->finalize(true), $algorithm, true);
    }

    public function hashFile(FilepathInterface $path, string|HashAlgorithm $algorithm = 'sha256'): HashInterface
    {
        $file = (string) $path;

        if (!is_file($file) || !is_readable($file)) {
            throw new \RuntimeException('Unable to read file: ' . $file);
        }

        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new \RuntimeException('Unable to read file: ' . $file);
        }

        return $this->hash($contents, $algorithm);
    }

    public function verify(string $data, HashInterface $expected, string|HashAlgorithm $algorithm = 'sha256'): bool
    {
        return $this->hash($data, $algorithm)->equals($expected);
    }

    public function verifyBinary(string $data, HashInterface $expected, string|HashAlgorithm $algorithm = 'sha256'): bool
    {
        return $this->hashBinary($data, $algorithm)->equals($expected);
    }

    /**
     * Hash a readable stream in fixed chunks without loading it fully into memory.
     *
     * @param resource $stream
     */
    public function hashStream($stream, string|HashAlgorithm $algorithm = 'sha256', int $chunkSize = 65536): HashInterface
    {
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new \InvalidArgumentException('hashStream() expects a stream resource');
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
}
