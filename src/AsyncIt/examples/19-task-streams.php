<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

$fixture = makeReferenceRuntime();
$runtime = $fixture['runtime'];
$scope = makeScope('task-stream-example');

echo "=== Task Streams (V.12) ===\n";

// V.12.1 / V.12.3 — stream() lazily spawns one task per generator yield,
// awaiting each to completion before pulling the next (backpressure).
$results = $runtime->run($scope, static function () use ($runtime): array {
    $collected = [];
    $stream = $runtime->stream(static function (): Generator {
        yield static fn (): int => 10;
        yield static fn (): int => 20;
        yield static fn (): int => 30;
    });

    $stream->rewind();
    while ($stream->valid()) {
        $collected[] = $stream->current();
        $stream->next();
    }

    return $collected;
});

echo '  stream results: [', implode(',', $results), "]\n";

// Empty generator produces no items.
$emptyResults = $runtime->run($scope, static function () use ($runtime): array {
    $collected = [];
    $stream = $runtime->stream(static function (): Generator {
        // Intentionally empty — no yields.
        yield from [];
    });

    $stream->rewind();
    while ($stream->valid()) {
        $collected[] = $stream->current();
        $stream->next();
    }

    return $collected;
});

echo '  empty stream results: [', implode(',', $emptyResults), "]\n";

echo "=== Task streams demonstrated ===\n";
echo "  - stream(): lazily spawns one task per generator yield\n";
echo "  - backpressure: next() blocks until current task reaches terminal state\n";
echo "  - empty generator produces no items\n";
