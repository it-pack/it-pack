<?php

declare(strict_types=1);

/**
 * Coverage isolation gate for Unit test classes.
 *
 * Verifies that every Unit test class declares at least one #[CoversClass]
 * attribute so coverage can be attributed to the class under test.
 *
 * Usage: php tools/coverage-isolation.php [tests/Unit directory]
 *
 * Exits 0 when every Unit test class has #[CoversClass], nonzero otherwise.
 */

$testsDirArg = $argv[1] ?? __DIR__ . '/../tests/Unit';
$testsDir = realpath($testsDirArg);

if ($testsDir === false || !is_dir($testsDir)) {
    fwrite(STDERR, "FAIL: tests directory not found: $testsDirArg\n");
    exit(2);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($testsDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

$missing = [];
$checked = 0;

foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    $basename = $file->getBasename('.php');
    if (!preg_match('/Test$/', $basename)) {
        continue;
    }
    $content = file_get_contents($path);
    if ($content === false) {
        fwrite(STDERR, "FAIL: could not read $path\n");
        exit(2);
    }
    ++$checked;
    if (
        !preg_match('/#\[CoversClass\(/', $content)
        && !preg_match('/#\[CoversTrait\(/', $content)
        && !preg_match('/#\[CoversNothing/', $content)
    ) {
        $missing[] = $path;
    }
}

if ($missing !== []) {
    fwrite(STDERR, "FAIL: $checked Unit test class(es) checked, " . count($missing)
        . " missing #[CoversClass]:\n");
    foreach ($missing as $path) {
        fwrite(STDERR, "  - $path\n");
    }
    exit(1);
}

fwrite(STDOUT, "PASS: $checked Unit test class(es) all declare #[CoversClass]\n");
exit(0);
