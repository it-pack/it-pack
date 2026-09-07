<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Service;

use ItPack\CommitIt\Contract\PathSanitizer;
use ItPack\CommitIt\Domain\Exception\PathScopeViolationException;

/**
 * Pure, side-effect free implementation of {@see PathSanitizer}.
 *
 * Resolves a relative or absolute path against the repository root and
 * ensures the canonical result never escapes the working-tree scope.
 * Symlinks are *not* resolved (the check is lexical) so the validation
 * itself performs no filesystem I/O — this keeps it safe for use in
 * hot paths and workers processing untrusted input.
 *
 * @api
 */
final readonly class DefaultPathSanitizer implements PathSanitizer
{
    public function sanitize(string $path, string $repoRoot): string
    {
        $realRepoRoot = realpath($repoRoot);

        if ($realRepoRoot === false) {
            throw new PathScopeViolationException(
                sprintf('Repository root "%s" is not accessible.', $repoRoot),
            );
        }

        $isAbsolute = str_starts_with($path, DIRECTORY_SEPARATOR);
        $split = preg_split('#[/\\\\]+#', $path, -1, PREG_SPLIT_NO_EMPTY);
        $segments = $split === false ? [] : $split;

        $stack = [];

        foreach ($segments as $segment) {
            if ($segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if ($stack === []) {
                    throw new PathScopeViolationException(
                        sprintf('Path "%s" escapes repository scope.', $path),
                    );
                }

                array_pop($stack);

                continue;
            }

            $stack[] = $segment;
        }

        $resolved = ($isAbsolute ? DIRECTORY_SEPARATOR : '') . implode(DIRECTORY_SEPARATOR, $stack);

        if ($isAbsolute) {
            $realResolved = realpath($resolved);

            if ($realResolved === false) {
                $realResolved = $resolved;
            }
        } else {
            $realResolved = $realRepoRoot . DIRECTORY_SEPARATOR . $resolved;
        }

        $separator = DIRECTORY_SEPARATOR;
        $inside = $realResolved === $realRepoRoot
            || str_starts_with($realResolved . $separator, $realRepoRoot . $separator);

        if (!$inside) {
            throw new PathScopeViolationException(
                sprintf('Path "%s" resolves outside the repository working-tree scope.', $path),
            );
        }

        return $resolved;
    }
}
