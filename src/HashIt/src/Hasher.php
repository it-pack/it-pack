<?php

declare(strict_types=1);

namespace ItPack\HashIt;

use InvalidArgumentException;
use ItPack\HashIt\AlgorithmSource\NativeAlgorithmSource;
use Random\RandomException;
use RuntimeException;
use Throwable;

final readonly class Hasher implements HasherInterface
{
    public function __construct(
        private ContextFactoryInterface $factory,
        private DirectoryListingInterface $listing,
        private ?HashingObserverInterface $observer = null,
        private ?AlgorithmSourceInterface $source = null,
    ) {
    }

    public function hash(string $data, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): HashInterface
    {
        $id = $this->observationId();
        $algorithmName = $this->algorithmName($algorithm);
        $this->observe(HashObservation::started(HashOperation::HASH, $id, $algorithmName, strlen($data)));
        $begin = hrtime(true);

        try {
            $hash = $this->hashString($data, $algorithm, false);
        } catch (Throwable $e) {
            $this->observe(HashObservation::failed($id, HashOperation::HASH, $e));
            throw $e;
        }

        $this->observe($this->completed($id, $algorithmName, strlen($data), $begin, $hash));

        return $hash;
    }

    public function hashBinary(string $data, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): HashInterface
    {
        $id = $this->observationId();
        $algorithmName = $this->algorithmName($algorithm);
        $this->observe(HashObservation::started(HashOperation::HASH_BINARY, $id, $algorithmName, strlen($data)));
        $begin = hrtime(true);

        try {
            $hash = $this->hashString($data, $algorithm, true);
        } catch (Throwable $e) {
            $this->observe(HashObservation::failed($id, HashOperation::HASH_BINARY, $e));
            throw $e;
        }

        $this->observe($this->completed($id, $algorithmName, strlen($data), $begin, $hash));

        return $hash;
    }

    public function hashFile(FilepathInterface $path, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): HashInterface
    {
        $id = $this->observationId();
        $algorithmName = $this->algorithmName($algorithm);
        $file = (string) $path;
        $total = $this->fileBytes($file);
        $this->observe(HashObservation::started(HashOperation::HASH_FILE, $id, $algorithmName, $total));
        $begin = hrtime(true);

        if (!is_file($file)) {
            $error = $this->unreadable($file);
            $this->observe(HashObservation::failed($id, HashOperation::HASH_FILE, $error));
            throw $error;
        }

        $handle = fopen($file, 'rb');
        if ($handle === false) {
            $error = $this->unreadable($file);
            $this->observe(HashObservation::failed($id, HashOperation::HASH_FILE, $error));
            throw $error;
        }

        try {
            $hash = $this->hashStream($handle, $algorithm);
        } finally {
            fclose($handle);
        }

        $this->observe($this->completed($id, $algorithmName, $total ?? 0, $begin, $hash));

        return $hash;
    }

    public function verify(string $data, HashInterface $expected, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): bool
    {
        $id = $this->observationId();
        $algorithmName = $this->algorithmName($algorithm);
        $this->observe(HashObservation::started(HashOperation::VERIFY, $id, $algorithmName, strlen($data)));
        $begin = hrtime(true);

        try {
            $hash = $this->hashString($data, $algorithm, false);
        } catch (Throwable $e) {
            $this->observe(HashObservation::failed($id, HashOperation::VERIFY, $e));
            throw $e;
        }

        $match = $hash->equals($expected);
        $this->observe($this->completed($id, $algorithmName, strlen($data), $begin, $hash));

        return $match;
    }

    public function verifyBinary(string $data, HashInterface $expected, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): bool
    {
        $id = $this->observationId();
        $algorithmName = $this->algorithmName($algorithm);
        $this->observe(HashObservation::started(HashOperation::VERIFY, $id, $algorithmName, strlen($data)));
        $begin = hrtime(true);

        try {
            $hash = $this->hashString($data, $algorithm, true);
        } catch (Throwable $e) {
            $this->observe(HashObservation::failed($id, HashOperation::VERIFY, $e));
            throw $e;
        }

        $match = $hash->equals($expected);
        $this->observe($this->completed($id, $algorithmName, strlen($data), $begin, $hash));

        return $match;
    }

    public function hashStream($stream, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256, int $chunkSize = self::DEFAULT_CHUNK_SIZE_BYTES, ?HashProgressInterface $progress = null): HashInterface
    {
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new InvalidArgumentException('hashStream() expects a stream resource');
        }

        if ($chunkSize < 1) {
            throw new InvalidArgumentException('Chunk size must be at least 1 byte');
        }

        $id = $this->observationId();
        $algorithmName = $this->algorithmName($algorithm);
        $total = $this->streamTotalBytes($stream);
        $this->observe(HashObservation::started(HashOperation::HASH_STREAM, $id, $algorithmName, $total));

        $builder = HashBuilder::start($this->factory, $algorithm);
        $position = ftell($stream);
        if ($position === false) {
            $position = 0;
        }
        fseek($stream, 0);

        $begin = hrtime(true);
        $processed = 0;

        try {
            while (!feof($stream)) {
                $chunk = fread($stream, $chunkSize);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $builder = $builder->append($chunk);
                $processed += strlen($chunk);
                $progress?->onBytes($processed, $total);
                $this->observe(HashObservation::progress($id, $processed, $total));
            }
        } catch (Throwable $e) {
            $this->observe(HashObservation::failed($id, HashOperation::HASH_STREAM, $e));
            throw $e;
        }

        fseek($stream, $position);

        $hash = $builder->digest();
        $this->observe($this->completed($id, $algorithmName, $processed, $begin, $hash));

        return $hash;
    }

    public function hashFiles(iterable $paths, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256, ?HashProgressInterface $progress = null): array
    {
        $id = $this->observationId();
        $algorithmName = $this->algorithmName($algorithm);
        $total = $progress === null ? null : $this->iterableTotalBytes($paths);
        $this->observe(HashObservation::started(HashOperation::HASH_FILES, $id, $algorithmName, $total));

        $begin = hrtime(true);
        $result = [];
        $processed = 0;

        try {
            foreach ($paths as $path) {
                $result[(string) $path] = $this->hashFile($path, $algorithm);

                $size = filesize((string) $path);
                $processed += $size === false ? 0 : $size;
                $progress?->onBytes($processed, $total);
                $this->observe(HashObservation::progress($id, $processed, $total));
            }
        } catch (Throwable $e) {
            $this->observe(HashObservation::failed($id, HashOperation::HASH_FILES, $e));
            throw $e;
        }

        $this->observe($this->completed($id, $algorithmName, $processed, $begin, null));

        return $result;
    }

    public function hashDirectory(string $directory, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256, ?HashProgressInterface $progress = null): array
    {
        if (!is_dir($directory)) {
            throw new RuntimeException('Not a directory: ' . $directory);
        }

        $id = $this->observationId();
        $algorithmName = $this->algorithmName($algorithm);
        $this->observe(HashObservation::started(HashOperation::HASH_DIRECTORY, $id, $algorithmName));

        $begin = hrtime(true);

        try {
            $result = $this->hashFiles($this->listing->regularFiles($directory), $algorithm, $progress);
        } catch (Throwable $e) {
            $this->observe(HashObservation::failed($id, HashOperation::HASH_DIRECTORY, $e));
            throw $e;
        }

        $this->observe($this->completed($id, $algorithmName, 0, $begin, null));

        return $result;
    }

    public function algorithms(): array
    {
        return $this->source()->algorithms();
    }

    public function supports(string $identifier): bool
    {
        return $this->source()->supports($identifier);
    }

    public function resolve(string $identifier): HashAlgorithm
    {
        return $this->source()->resolve($identifier);
    }

    private function source(): AlgorithmSourceInterface
    {
        return $this->source ?? new NativeAlgorithmSource();
    }

    private function hashString(string $data, string|HashAlgorithm $algorithm, bool $binary): HashInterface
    {
        $algorithm = $algorithm instanceof HashAlgorithm ? $algorithm->value : $algorithm;
        $context = $this->factory->build($algorithm);
        $context->update($data);

        return new Hash($context->finalize($binary), $algorithm, $binary);
    }

    private function fileBytes(string $file): ?int
    {
        if (!is_file($file)) {
            return null;
        }

        $size = filesize($file);

        return $size === false ? null : $size;
    }

    private function completed(string $id, string $algorithm, int $bytes, int $beginNs, ?HashInterface $hash): HashObservation
    {
        $durationMs = (int) round((hrtime(true) - $beginNs) / 1_000_000);

        return HashObservation::completed($id, $algorithm, $bytes, $durationMs, $hash?->toUrn());
    }

    private function observe(HashObservation $observation): void
    {
        $this->observer?->observe($observation);
    }

    /**
     * @throws RandomException
     */
    private function observationId(): string
    {
        return bin2hex(random_bytes(8));
    }

    private function algorithmName(string|HashAlgorithm $algorithm): string
    {
        return $algorithm instanceof HashAlgorithm ? $algorithm->value : $algorithm;
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
