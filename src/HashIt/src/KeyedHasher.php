<?php

declare(strict_types=1);

namespace ItPack\HashIt;

use InvalidArgumentException;
use ItPack\HashIt\AlgorithmSource\NativeAlgorithmSource;
use ItPack\HashIt\Observability\HashingInstrumentation;
use Throwable;

/**
 * @property-read ?Contract\HashingObserverInterface $observer supplied by the constructor; declared
 *               here so static analysis (PHPStan, intelephense) sees the collaborator that the
 *               {@see HashingInstrumentation} trait reads via `$this->observer?->observe()`.
 */
final readonly class KeyedHasher implements Contract\KeyedHasherInterface
{
    use HashingInstrumentation;

    public function __construct(
        private Contract\KeyedContextFactoryInterface $factory,
        private ?Contract\HashingObserverInterface $observer = null,
        private ?Contract\AlgorithmSourceInterface $source = null,
    ) {
    }

    public function hmac(
        string $data,
        string $key,
        string|HashAlgorithm $algorithm = HashAlgorithm::SHA256,
    ): Contract\HashInterface {
        $id = $this->observationId();
        $algorithmName = $this->algorithmName($algorithm);
        $this->observe(HashObservation::started(HashOperation::HMAC, $id, $algorithmName, strlen($data)));
        $begin = hrtime(true);

        try {
            $hash = $this->hashString($data, $key, $algorithm, false);
        } catch (Throwable $e) {
            $this->observe(HashObservation::failed($id, HashOperation::HMAC, $e));
            throw $e;
        }

        $this->observe($this->completed($id, $algorithmName, strlen($data), $begin, $hash));

        return $hash;
    }

    public function hmacBinary(
        string $data,
        string $key,
        string|HashAlgorithm $algorithm = HashAlgorithm::SHA256,
    ): Contract\HashInterface {
        $id = $this->observationId();
        $algorithmName = $this->algorithmName($algorithm);
        $this->observe(HashObservation::started(HashOperation::HMAC, $id, $algorithmName, strlen($data)));
        $begin = hrtime(true);

        try {
            $hash = $this->hashString($data, $key, $algorithm, true);
        } catch (Throwable $e) {
            $this->observe(HashObservation::failed($id, HashOperation::HMAC, $e));
            throw $e;
        }

        $this->observe($this->completed($id, $algorithmName, strlen($data), $begin, $hash));

        return $hash;
    }

    public function hmacFile(
        Contract\FilepathInterface $path,
        string $key,
        string|HashAlgorithm $algorithm = HashAlgorithm::SHA256,
    ): Contract\HashInterface {
        $id = $this->observationId();
        $algorithmName = $this->algorithmName($algorithm);
        $file = (string) $path;
        $total = $this->fileBytes($file);
        $this->observe(HashObservation::started(HashOperation::HMAC_FILE, $id, $algorithmName, $total));
        $begin = hrtime(true);

        if (!is_file($file)) {
            $error = $this->unreadable($file);
            $this->observe(HashObservation::failed($id, HashOperation::HMAC_FILE, $error));
            throw $error;
        }

        $handle = fopen($file, 'rb');
        if ($handle === false) {
            $error = $this->unreadable($file);
            $this->observe(HashObservation::failed($id, HashOperation::HMAC_FILE, $error));
            throw $error;
        }

        try {
            $hash = $this->hmacStream($handle, $key, $algorithm);
        } finally {
            fclose($handle);
        }

        $this->observe($this->completed($id, $algorithmName, $total ?? 0, $begin, $hash));

        return $hash;
    }

    public function verify(
        string $data,
        string $key,
        Contract\HashInterface $expected,
        string|HashAlgorithm $algorithm = HashAlgorithm::SHA256,
    ): bool {
        $id = $this->observationId();
        $algorithmName = $this->algorithmName($algorithm);
        $this->observe(HashObservation::started(HashOperation::VERIFY, $id, $algorithmName, strlen($data)));
        $begin = hrtime(true);

        try {
            $hash = $this->hashString($data, $key, $algorithm, false);
        } catch (Throwable $e) {
            $this->observe(HashObservation::failed($id, HashOperation::VERIFY, $e));
            throw $e;
        }

        $match = $hash->equals($expected);
        $this->observe($this->completed($id, $algorithmName, strlen($data), $begin, $hash));

        return $match;
    }

    public function verifyBinary(
        string $data,
        string $key,
        Contract\HashInterface $expected,
        string|HashAlgorithm $algorithm = HashAlgorithm::SHA256,
    ): bool {
        $id = $this->observationId();
        $algorithmName = $this->algorithmName($algorithm);
        $this->observe(HashObservation::started(HashOperation::VERIFY, $id, $algorithmName, strlen($data)));
        $begin = hrtime(true);

        try {
            $hash = $this->hashString($data, $key, $algorithm, true);
        } catch (Throwable $e) {
            $this->observe(HashObservation::failed($id, HashOperation::VERIFY, $e));
            throw $e;
        }

        $match = $hash->equals($expected);
        $this->observe($this->completed($id, $algorithmName, strlen($data), $begin, $hash));

        return $match;
    }

    public function hmacStream(
        $stream,
        string $key,
        string|HashAlgorithm $algorithm = HashAlgorithm::SHA256,
        int $chunkSize = Contract\HasherInterface::DEFAULT_CHUNK_SIZE_BYTES,
        ?Contract\HashProgressInterface $progress = null,
    ): Contract\HashInterface {
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new InvalidArgumentException('hmacStream() expects a stream resource');
        }

        if ($chunkSize < 1) {
            throw new InvalidArgumentException('Chunk size must be at least 1 byte');
        }

        $id = $this->observationId();
        $algorithmName = $this->algorithmName($algorithm);
        $total = $this->streamTotalBytes($stream);
        $this->observe(HashObservation::started(HashOperation::HMAC_STREAM, $id, $algorithmName, $total));

        $builder = HashBuilder::fromContext($this->factory->build($algorithm, $key), $algorithm);
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
            $this->observe(HashObservation::failed($id, HashOperation::HMAC_STREAM, $e));
            throw $e;
        }

        fseek($stream, $position);

        $hash = $builder->digest();
        $this->observe($this->completed($id, $algorithmName, $processed, $begin, $hash));

        return $hash;
    }

    public function hmacFiles(
        iterable $paths,
        string $key,
        string|HashAlgorithm $algorithm = HashAlgorithm::SHA256,
        ?Contract\HashProgressInterface $progress = null,
    ): array {
        $id = $this->observationId();
        $algorithmName = $this->algorithmName($algorithm);
        $total = $progress === null ? null : $this->iterableTotalBytes($paths);
        $this->observe(HashObservation::started(HashOperation::HMAC_FILES, $id, $algorithmName, $total));

        $begin = hrtime(true);
        $result = [];
        $processed = 0;

        try {
            foreach ($paths as $path) {
                $result[(string) $path] = $this->hmacFile($path, $key, $algorithm);

                $size = filesize((string) $path);
                $processed += $size === false ? 0 : $size;
                $progress?->onBytes($processed, $total);
                $this->observe(HashObservation::progress($id, $processed, $total));
            }
        } catch (Throwable $e) {
            $this->observe(HashObservation::failed($id, HashOperation::HMAC_FILES, $e));
            throw $e;
        }

        $this->observe($this->completed($id, $algorithmName, $processed, $begin, null));

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

    private function source(): Contract\AlgorithmSourceInterface
    {
        return $this->source ?? new NativeAlgorithmSource();
    }

    private function hashString(
        string $data,
        string $key,
        string|HashAlgorithm $algorithm,
        bool $binary,
    ): Contract\HashInterface {
        $algorithm = $algorithm instanceof HashAlgorithm ? $algorithm->value : $algorithm;
        $context = $this->factory->build($algorithm, $key);
        $context->update($data);

        return new Hash($context->finalize($binary), $algorithm, $binary);
    }
}
