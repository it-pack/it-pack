<?php

declare(strict_types=1);

namespace Tools\PhpStan\AsyncItRules\Support;

use PHPStan\Analyser\Scope;

/**
 * Base for AsyncIt static-analysis rules that should only be enforced against the
 * library source tree (`src/`), not the test suite or examples.
 *
 * @internal
 */
abstract class AbstractSrcRule
{
    public function __construct(protected readonly string $srcRoot)
    {
    }

    protected function isInSrc(Scope $scope): bool
    {
        $file = realpath($scope->getFile());

        if ($file === false) {
            $file = $scope->getFile();
        }

        return str_starts_with($file, rtrim($this->srcRoot, '/') . '/');
    }

    /**
     * Path of the analysed file relative to the project root, prefixed with the
     * `src/` segment (e.g. `src/Domain/Execution/Execution.php`). Files outside
     * the project (e.g. the rule sources themselves) return their absolute path.
     * Useful for directory- or file-based exemptions expressed as `src/...`.
     */
    protected function relativePath(Scope $scope): string
    {
        $file = $scope->getFile();
        $root = rtrim($this->srcRoot, '/');
        $projectRoot = dirname($root);

        if (str_starts_with($file, $projectRoot . '/')) {
            return substr($file, strlen($projectRoot) + 1);
        }

        return $file;
    }
}
