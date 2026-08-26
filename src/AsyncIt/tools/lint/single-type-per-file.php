<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

$root = dirname(__DIR__, 2);
$src = $root . '/src';

$parser = (new PhpParser\ParserFactory())->createForNewestSupportedVersion();

$violations = 0;

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $code = file_get_contents($file->getPathname());
    if ($code === false) {
        continue;
    }

    try {
        $ast = $parser->parse($code);
    } catch (Throwable) {
        continue;
    }

    if ($ast === null) {
        continue;
    }

    $count = 0;
    foreach ($ast as $node) {
        if (
            $node instanceof PhpParser\Node\Stmt\Class_
            || $node instanceof PhpParser\Node\Stmt\Interface_
            || $node instanceof PhpParser\Node\Stmt\Trait_
            || $node instanceof PhpParser\Node\Stmt\Enum_
        ) {
            $count++;
        }
    }

    if ($count > 1) {
        fwrite(STDERR, sprintf("%s declares %d types (one type per file required)\n", $file->getPathname(), $count));
        $violations++;
    }
}

if ($violations > 0) {
    fwrite(STDERR, sprintf("%d single-type-per-file violation(s)\n", $violations));
    exit(1);
}

echo "single-type-per-file: OK\n";
