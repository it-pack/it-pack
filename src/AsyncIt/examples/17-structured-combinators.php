<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

use ItPack\RunIt\Domain\Shared\Duration;

$fixture = makeReferenceRuntime();
$runtime = $fixture['runtime'];
$scope = makeScope('combinators-example');

echo "=== Structured Combinators (V.10) ===\n";

// V.10.1/10.3 — combinators run inside AsyncRuntime::run(), which provides
// the active scheduler context needed to spawn and await child tasks.

// race: runs all operations concurrently, returns the result of the first to
// complete, cancels remaining children.
$raceResult = $runtime->run($scope, static fn (): string => $runtime->race([
    static function () use ($runtime): string {
        $runtime->awaitDelay(Duration::ofMilliseconds(100));
        return 'slow';
    },
    static function () use ($runtime): string {
        $runtime->awaitDelay(Duration::ofMilliseconds(10));
        return 'fast';
    },
]));
echo '  race() returned: ', $raceResult, "\n";

// all: runs all operations concurrently, returns all results in spawn order.
$allResults = $runtime->run($scope, static fn (): array => $runtime->all([
    static fn (): string => 'a',
    static fn (): string => 'b',
    static fn (): string => 'c',
]));
echo '  all() returned: [', implode(',', $allResults), "]\n";

// any: returns the result of the first child to SUCCEED, ignoring failures of
// other children; throws if all fail.
$anyResult = $runtime->run($scope, static fn (): string => $runtime->any([
    static fn (): string => 'first',
    static fn (): string => 'second',
]));
echo '  any() returned: ', $anyResult, "\n";

// select: returns the winning task and its result.
$selectResult = $runtime->run($scope, static fn (): array => $runtime->select([
    static function () use ($runtime): string {
        $runtime->awaitDelay(Duration::ofMilliseconds(100));
        return 'slow';
    },
    static function () use ($runtime): string {
        $runtime->awaitDelay(Duration::ofMilliseconds(10));
        return 'fast';
    },
]));
echo '  select() winning task result: ', $selectResult['result'], "\n";
echo '  select() winning task id: ', $selectResult['task']->id()->value, "\n";

// join: runs all operations concurrently and waits for all to complete.
$runtime->run($scope, static function () use ($runtime): void {
    $runtime->join([
        static function () use ($runtime): void {
            $runtime->awaitDelay(Duration::ofMilliseconds(10));
            echo "  [join-task-1] done\n";
        },
        static function () use ($runtime): void {
            $runtime->awaitDelay(Duration::ofMilliseconds(5));
            echo "  [join-task-2] done\n";
        },
    ]);
});
echo "  join() completed\n";

echo "=== Structured combinators demonstrated ===\n";
echo "  - race(...): first to complete wins, cancels remaining children\n";
echo "  - all(...): all results in spawn order\n";
echo "  - any(...): first to succeed wins, ignores failures\n";
echo "  - select(...): winning task + result pair\n";
echo "  - join(...): fire-and-forget, waits for all\n";
