<?php

declare(strict_types=1);

namespace ItPack\HashIt;

use InvalidArgumentException;
use ItPack\HashIt\AlgorithmSource\NativeAlgorithmSource;
use ItPack\HashIt\Observability\HashingInstrumentation;
use RuntimeException;
use Throwable;

/**
 * @property-read ?Contract\HashingObserverInterface $observer supplied by the constructor; declared
 *               here so static analysis (PHPStan, intelephense) sees the collaborator that the
 *               {@see HashingInstrumentation} trait reads via `$this->observer?->observe()`.
 */
final readonly class Hasher implements Contract\HasherInterface
{
    use HashingInstrumentation;

    public function __construct(
        private Contract\ContextFactoryInterface $factory,
        private Contract\DirectoryListingInterface $listing,
        private ?Contract\HashingObserverInterface $observer = null,
        private ?Contract\AlgorithmSourceInterface $source = null,
    ) {
    }

    public function hash(string $data, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): Contract\HashInterface
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

    public function hashBinary(
        string $data,
        string|HashAlgorithm $algorithm = HashAlgorithm::SHA256,
    ): Contract\HashInterface {
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

    public function hashFile(
        Contract\FilepathInterface $path,
        string|HashAlgorithm $algorithm = HashAlgorithm::SHA256,
    ): Contract\HashInterface {
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

    public function verify(
        string $data,
        Contract\HashInterface $expected,
        string|HashAlgorithm $algorithm = HashAlgorithm::SHA256,
    ): bool {
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

    public function verifyBinary(
        string $data,
        Contract\HashInterface $expected,
        string|HashAlgorithm $algorithm = HashAlgorithm::SHA256,
    ): bool {
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

    public function hashStream(
        $stream,
        string|HashAlgorithm $algorithm = HashAlgorithm::SHA256,
        int $chunkSize = self::DEFAULT_CHUNK_SIZE_BYTES,
        ?Contract\HashProgressInterface $progress = null,
    ): Contract\HashInterface {
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

    public function hashFiles(
        iterable $paths,
        string|HashAlgorithm $algorithm = HashAlgorithm::SHA256,
        ?Contract\HashProgressInterface $progress = null,
    ): array {
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

    public function hashDirectory(
        string $directory,
        string|HashAlgorithm $algorithm = HashAlgorithm::SHA256,
        ?Contract\HashProgressInterface $progress = null,
    ): array {
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

    public function hashInput(
        Contract\HashInputInterface $input,
        string|HashAlgorithm $algorithm = HashAlgorithm::SHA256,
    ): Contract\HashInterface {
        $id = $this->observationId();
        $algorithmName = $this->algorithmName($algorithm);
        $this->observe(HashObservation::started(HashOperation::HASH_INPUT, $id, $algorithmName, null));
        $begin = hrtime(true);

        try {
            $sink = $input->writeTo(HashBuilder::start($this->factory, $algorithm));
            if (!$sink instanceof HashBuilder) {
                throw new RuntimeException('HashInput did not return the expected hashing builder');
            }
            $hash = $sink->digest();
        } catch (Throwable $e) {
            $this->observe(HashObservation::failed($id, HashOperation::HASH_INPUT, $e));
            throw $e;
        }

        $this->observe($this->completed($id, $algorithmName, 0, $begin, $hash));

        return $hash;
    }

    private function source(): Contract\AlgorithmSourceInterface
    {
        return $this->source ?? new NativeAlgorithmSource();
    }

    private function hashString(
        string $data,
        string|HashAlgorithm $algorithm,
        bool $binary,
    ): Contract\HashInterface {
        $algorithm = $algorithm instanceof HashAlgorithm ? $algorithm->value : $algorithm;
        $context = $this->factory->build($algorithm);
        $context->update($data);

        return new Hash($context->finalize($binary), $algorithm, $binary);
    }
}
