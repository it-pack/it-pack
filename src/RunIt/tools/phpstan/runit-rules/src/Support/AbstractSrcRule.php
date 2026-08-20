<?php

declare(strict_types=1);

namespace Tools\PhpStan\RunitRules\Support;

use PHPStan\Analyser\Scope;

/**
 * Base for RunIt static-analysis rules that should only be enforced against the
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
}
