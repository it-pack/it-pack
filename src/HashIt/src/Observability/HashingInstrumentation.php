<?php

declare(strict_types=1);

namespace ItPack\HashIt\Observability;

use ItPack\HashIt\Contract\FilepathInterface;
use ItPack\HashIt\Contract\HashInterface;
use ItPack\HashIt\HashAlgorithm;
use ItPack\HashIt\HashObservation;
use Random\RandomException;
use RuntimeException;

/**
 * Shared observation / progress / byte-sizing helpers used by both
 * {@see \ItPack\HashIt\Hasher} and {@see \ItPack\HashIt\KeyedHasher}.
 *
 * Pure duplication removal: every method here was previously defined
 * verbatim in both classes. The trait depends only on the same private
 * collaborators the using class already holds (`$observer` of type
 * `?Contract\HashingObserverInterface`); no constructor or property
 * changes are required on the host.
 */
trait HashingInstrumentation
{
    private function completed(
        string $id,
        string $algorithm,
        int $bytes,
        int $beginNs,
        ?HashInterface $hash,
    ): HashObservation {
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

    private function fileBytes(string $file): ?int
    {
        if (!is_file($file)) {
            return null;
        }

        $size = filesize($file);

        return $size === false ? null : $size;
    }

    /**
     * Total bytes of a stream when it is seekable (so its size is known), otherwise null.
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
