<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

$root = dirname(__DIR__, 2);
$src = $root . '/src';
$test = $root . '/tests';

$violations = 0;

/**
 * Recursively count top-level type declarations (class, interface, trait, enum).
 *
 * Classes inside expression context (e.g. anonymous classes via `new class {}`)
 * are not counted, since they are not top-level types.
 */
function countTypes(array $nodes, bool $inExpression = false): int
{
    $count = 0;

    foreach ($nodes as $node) {
        if (!$inExpression &&
            ($node instanceof PhpParser\Node\Stmt\Class_
            || $node instanceof PhpParser\Node\Stmt\Interface_
            || $node instanceof PhpParser\Node\Stmt\Trait_
            || $node instanceof PhpParser\Node\Stmt\Enum_)
        ) {
            $count++;
        }

        // Children of expression nodes are in the expression context.
        $childInExpression = $inExpression || $node instanceof PhpParser\Node\Expr;

        foreach ($node->getSubNodeNames() as $subNodeName) {
            $subNode = $node->$subNodeName;
            if (is_array($subNode)) {
                $count += countTypes($subNode, $childInExpression);
            } elseif ($subNode instanceof PhpParser\Node) {
                $count += countTypes([$subNode], $childInExpression);
            }
        }
    }

    return $count;
}

function findViolations(string $path): int
{
    $violations = 0;

    $parser = (new PhpParser\ParserFactory())->createForNewestSupportedVersion();

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
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

        $count = countTypes($ast);

        if ($count > 1) {
            fwrite(STDERR, sprintf("%s declares %d types (one type per file required)\n", $file->getPathname(), $count));
            $violations++;
        }
    }

    return $violations;
}

$violations += findViolations($src);
$violations += findViolations($test);

if ($violations > 0) {
    fwrite(STDERR, sprintf("%d single-type-per-file violation(s)\n", $violations));
    exit(1);
}

echo "single-type-per-file: OK\n";
