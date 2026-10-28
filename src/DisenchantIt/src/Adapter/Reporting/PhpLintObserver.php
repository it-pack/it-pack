<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Adapter\Reporting;

use ItPack\DisenchantIt\Contract\Reporting\VerificationObserverInterface;
use ItPack\DisenchantIt\Exception\VerificationException;

/**
 * VerificationObserverInterface that lints the rewritten source in a SEPARATE
 * process via `php -l` on a throwaway temp file. Core never executes or
 * autoloads the analysed source — this adapter shells out and reads the lint
 * result back, exactly the "verify out-of-process" boundary AGENTS.md requires.
 *
 * A lint failure throws {@see VerificationException} carrying the linter output.
 * The temp file is always removed (best-effort) even when lint fails.
 */
final class PhpLintObserver implements VerificationObserverInterface
{
    public function __construct(
        private string $phpBinary = PHP_BINARY,
    ) {
    }

    public function afterRewrite(string $rewrittenSource): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'disenchant_lint_');
        if ($tmp === false) {
            throw new VerificationException('Could not create a temp file for php -l verification.');
        }

        try {
            /** @phpstan-ignore-next-line */
            if (file_put_contents($tmp, $rewrittenSource) === false) {
                throw new VerificationException('Could not write rewritten source for php -l verification.');
            }

            $result = exec(
                sprintf('%s -l %s 2>&1', escapeshellarg($this->phpBinary), escapeshellarg($tmp)),
                $output,
                $exitCode,
            );

            if ($exitCode !== 0) {
                throw new VerificationException(
                    /** @phpstan-ignore-next-line */
                    'Rewritten source failed `php -l`: ' . implode("\n", $output),
                );
            }

            // Suppress unused-variable warning from some PHPStan/static configs.
            unset($result);
        } finally {
            if (is_file($tmp)) {
                /** @phpstan-ignore-next-line */
                unlink($tmp);
            }
        }
    }
}
