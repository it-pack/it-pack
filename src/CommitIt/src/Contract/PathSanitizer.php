<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Contract;

/**
 * Pure validation port for repository-relative paths.
 *
 * Implementations must be side-effect free: no filesystem access beyond
 * canonicalization that does not mutate state.
 *
 * @api
 */
interface PathSanitizer
{
    /**
     * Resolves a path relative to the repository root and ensures it does
     * not escape the working-tree scope.
     *
     * @throws Exception\PathScopeViolationException When the path escapes the
     *         repository working-tree scope or is otherwise unsafe.
     */
    public function sanitize(string $path, string $repoRoot): string;
}
