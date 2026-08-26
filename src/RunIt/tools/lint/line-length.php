<?php

declare(strict_types=1);

namespace Tools\Lint;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Line-length guard (plan T3.3): fails on any code line longer than 120 columns
 * in `src`, `tests`, and `tools` (excluding vendored stubs). Docblock and comment lines are exempt; generated
 * Codeception artifacts (`tests/Support/_generated`) are ignored.
 *
 * Run via `composer lint:length`; wired into the `quality` gate chain.
 */
final class LineLengthLint
{
    private const int LIMIT = 120;

    private const string ROOT = __DIR__ . '/../..';

    /**
     * @return list<string> relative paths of offending files with details
     */
    public function violations(): array
    {
        $violations = [];

        foreach (['src', 'tests', 'tools'] as $dir) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(self::ROOT . '/' . $dir),
            );

            foreach ($iterator as $file) {
                assert($file instanceof SplFileInfo);

                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $path = $file->getPathname();

                if (str_contains($path, DIRECTORY_SEPARATOR . '_generated' . DIRECTORY_SEPARATOR)
                    || str_contains($path, DIRECTORY_SEPARATOR . '_output' . DIRECTORY_SEPARATOR)
                    || str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) {
                    continue;
                }

                foreach ($this->longLines($path) as [$line, $length]) {
                    $violations[] = sprintf(
                        '%s:%d: line is %d characters (limit %d)',
                        substr($path, strlen(self::ROOT) + 1),
                        $line,
                        $length,
                        self::LIMIT,
                    );
                }
            }
        }

        return $violations;
    }

    /**
     * @return list<array{int, int}>
     */
    private function longLines(string $path): array
    {
        $found = [];

        foreach (file($path) ?: [] as $index => $line) {
            $trimmed = ltrim($line);

            if ($trimmed === '' || $trimmed[0] === '*' || $trimmed[0] === '#') {
                continue;
            }

            if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '/*')) {
                continue;
            }

            $length = mb_strlen(rtrim($line, "\r\n"));

            if ($length > self::LIMIT) {
                $found[] = [$index + 1, $length];
            }
        }

        return $found;
    }
}

$lint = new LineLengthLint();
$violations = $lint->violations();

foreach ($violations as $violation) {
    echo $violation, PHP_EOL;
}

printf(
    '%s (%d violation(s), limit %d columns)%s',
    $violations === [] ? 'OK' : 'FAIL',
    count($violations),
    120,
    PHP_EOL,
);

exit($violations === [] ? 0 : 1);
