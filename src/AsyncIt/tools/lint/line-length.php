<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$limit = 120;
$src = $root . '/src';

$violations = 0;

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $lines = file($file->getPathname(), FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        continue;
    }

    foreach ($lines as $index => $line) {
        if (strlen($line) > $limit) {
            fwrite(STDERR, sprintf("%s:%d exceeds %d chars (%d)\n", $file->getPathname(), $index + 1, $limit, strlen($line)));
            $violations++;
        }
    }
}

if ($violations > 0) {
    fwrite(STDERR, sprintf("%d line-length violation(s)\n", $violations));
    exit(1);
}

echo "line-length: OK\n";
