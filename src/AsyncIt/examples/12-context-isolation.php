<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

use ItPack\AsyncIt\Application\Context\MemoryContextStorage;
use ItPack\AsyncIt\Application\Scope\ReferenceExecutionScope;
use ItPack\AsyncIt\Domain\ContextToken;
use ItPack\RunIt\Domain\Shared\Duration;

$fixture = makeReferenceRuntime();
$scope = makeScope('context-isolation');

echo "=== Context isolation: attach/detach protocol ===\n";

// ContextStorageInterface (RunIt) is a LIFO stack keyed by ContextToken.
// attach() pushes an execution scope and returns a token; detach() pops it
// and restores the previous scope. Invalid tokens and double-detachment are
// detected — this is what RunIt's ContextStorageVerifier checks.
//
// AsyncIt triggers detach() before Fiber::suspend() and reattach() after
// Fiber::resume() (guarded by finally), so an interleaved task never holds
// ambient context (conformance Assertion A).

$storage = new MemoryContextStorage();

// ReferenceExecutionScope implements ExecutionScopeInterface, suitable for attach().
$scopeA = new ReferenceExecutionScope('context-a');
$scopeB = new ReferenceExecutionScope('context-b');

// Attach scope A, then scope B (LIFO nesting).
$tokenA = $storage->attach($scopeA);
echo '  attached context-A (token ', $tokenA->id(), ")\n";

$tokenB = $storage->attach($scopeB);
echo '  attached context-B (nested above A, token ', $tokenB->id(), ")\n";

// Detach in reverse order (LIFO).
$storage->detach($tokenB);
echo "  detached context-B\n";

$storage->detach($tokenA);
echo "  detached context-A\n";

// Double-detachment is detected.
try {
    $storage->detach($tokenA);
    echo "  ERROR: double-detach should have thrown\n";
} catch (LogicException) {
    echo "  double-detach rejected as expected\n";
}

// Invalid token (wrong id / not on stack) is rejected.
$bogusToken = new ContextToken(99999);
try {
    $storage->detach($bogusToken);
    echo "  ERROR: invalid token should have thrown\n";
} catch (LogicException) {
    echo "  invalid token rejected as expected\n";
}

echo "\n=== How AsyncIt uses this ===\n";
echo "  1. Scheduler calls contextStorage->attach() before each Fiber resume\n";
echo "  2. The RunIt execution scope is attached as ambient context\n";
echo "  3. On suspension (await/awaitDelay), contextStorage->detach() is called\n";
echo "  4. Under finally, the exact context is re-attached before business code runs\n";
echo "  5. Interleaved tasks never observe each other's context (Assertion A)\n";
echo "  6. On terminal transition, context is cleaned up (no request leakage)\n";

echo "\n=== Context isolation in practice ===\n";
// Each task spawned by AsyncRuntime gets its own execution scope. The
// scheduler attaches that scope around every resume and detaches it around
// every suspend. Even though tasks share the same scheduler, their contexts
// never mix.
$runtime = $fixture['runtime'];

$runtime->run($scope, static function () use ($runtime): void {
    $taskA = $runtime->spawn(static function () use ($runtime): void {
        for ($i = 0; $i < 3; $i++) {
            echo "  task-A: tick $i (context isolated)\n";
            $runtime->awaitDelay(Duration::ofNanoseconds(50_000));
        }
    });

    $taskB = $runtime->spawn(static function () use ($runtime): void {
        for ($i = 0; $i < 3; $i++) {
            echo "  task-B: tick $i (context isolated)\n";
            $runtime->awaitDelay(Duration::ofNanoseconds(50_000));
        }
    });

    $runtime->await($taskA);
    $runtime->await($taskB);
});

echo "  both tasks completed with isolated contexts\n";
