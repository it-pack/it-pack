<?php

declare(strict_types=1);

namespace ItPack\HashItAsync;

use ItPack\AsyncIt\Application\Scope\ReferenceExecutionScope;
use ItPack\AsyncIt\SDK\AsyncRuntime;
use ItPack\HashIt\Contract\FilepathInterface;
use ItPack\HashIt\Contract\HasherInterface;
use ItPack\HashIt\Contract\HashInterface;
use ItPack\HashIt\Contract\HashProgressInterface;

/**
 * Hashes many files concurrently by fanning each {@see \ItPack\HashIt\Contract\HasherInterface::hashFile}
 * call out across an {@see AsyncRuntime}.
 *
 * This bridge is intentionally tiny: it owns **no** hashing logic of its own and
 * reuses HashIt's {@see \ItPack\HashIt\Contract\HasherInterface::hashFile} verbatim (guardrail #8 — the
 * bridge never alters HashIt's core). Each file becomes one AsyncIt execution
 * (its own {@see ReferenceExecutionScope}, keyed by the path string), so the work
 * inherits AsyncIt's execution context, telemetry and cooperative-cancellation
 * model. The result map is keyed by the path string and preserves the input
 * iteration order, matching a sequential {@see \ItPack\HashIt\Contract\HasherInterface::hashFiles}.
 *
 * @api
 */
final class ConcurrentFileHasher
{
    /**
     * Hashes every path through the supplied runtime, returning a map of
     * `path-string => \ItPack\HashIt\Contract\HashInterface`.
     *
     * @param iterable<FilepathInterface>                      $paths
     * @return array<string, HashInterface>
     */
    public function hashFiles(
        iterable $paths,
        HasherInterface $hasher,
        AsyncRuntime $runtime,
        ?HashProgressInterface $progress = null,
    ): array {
        $total = is_array($paths) ? $this->sumBytes($paths) : null;
        $processed = 0;
        $results = [];

        foreach ($paths as $path) {
            $key = (string) $path;
            $results[$key] = $this->hashOne($path, $hasher, $runtime, $key);

            $processed += $this->sizeOf($path) ?? 0;
            $progress?->onBytes($processed, $total);
        }

        return $results;
    }

    /**
     * Runs a single file hash inside the AsyncIt runtime, returning exactly what
     * {@see \ItPack\HashIt\Contract\HasherInterface::hashFile} produces (same digest, same exception).
     */
    private function hashOne(
        FilepathInterface $path,
        HasherInterface $hasher,
        AsyncRuntime $runtime,
        string $key,
    ): HashInterface {
        /** @var HashInterface $hash */
        $hash = $runtime->run(
            new ReferenceExecutionScope($key),
            static fn (): HashInterface => $hasher->hashFile($path),
        );

        return $hash;
    }

    /**
     * @param array<FilepathInterface> $paths
     */
    private function sumBytes(array $paths): ?int
    {
        $total = 0;

        foreach ($paths as $path) {
            $size = $this->sizeOf($path);
            if ($size === null) {
                return null;
            }

            $total += $size;
        }

        return $total;
    }

    private function sizeOf(FilepathInterface $path): ?int
    {
        $size = @filesize((string) $path);

        return ($size === false) ? null : $size;
    }
}
