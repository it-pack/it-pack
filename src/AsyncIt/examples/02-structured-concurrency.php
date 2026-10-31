<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

use ItPack\AsyncIt\Application\Factory\CancellationScopeFactory;
use ItPack\AsyncIt\Application\Factory\TaskGroupFactory;
use ItPack\AsyncIt\Contract\Port\BackpressurePolicy;
use ItPack\AsyncIt\Domain\Task;
use ItPack\AsyncIt\Domain\TaskGroup;
use ItPack\AsyncIt\Domain\TaskId;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Shared\Duration;

$fixture = makeReferenceRuntime();
$runtime = $fixture['runtime'];
$scope = makeScope('structured-concurrency');
$scheduler = $fixture['scheduler'];

echo "=== spawn() + await() ===\n";

// Spawn a child task from within a running execution, then await its result.
$value = $runtime->run($scope, static function () use ($runtime): int {
    $task = $runtime->spawn(static fn (): int => 42);

    return $runtime->await($task);
});

echo 'spawned task returned: ', $value, "\n";

echo "\n=== TaskGroup with go() / await() ===\n";

// A TaskGroup scopes child tasks that share one cancellation scope.
// If any child fails, the group cancels the scope and cascades to siblings.
// The group itself is a TaskInterface awaitable like any task.
// Use the scheduler directly (dispatch = submit + run + result) so the
// TaskGroup shares the same scheduler instance as the running reactor.
$taskId = new TaskId('group-root');
$executionId = ExecutionId::fromString('group-exec');

$groupFactory = new TaskGroupFactory(new CancellationScopeFactory());

$results = $scheduler->dispatch(
    new Task(
        new TaskId('group-root-task'),
        $executionId,
        $fixture['rootScope'],
    ),
    static function () use ($scheduler, $taskId, $executionId, $groupFactory): array {
        $group = $groupFactory->create($taskId, $executionId, $scheduler);

        for ($i = 1; $i <= 3; $i++) {
            $index = $i;
            $group->go(static function () use ($scheduler, $index): string {
                $scheduler->awaitDelay(Duration::ofNanoseconds(500_000 * $index));

                return "task-$index-done";
            });
        }

        return $group->await();
    },
);

echo 'parallel tasks completed: ';
echo implode(', ', $results), "\n";

echo "\n=== TaskGroup with maxConcurrency (BackpressurePolicy::Block) ===\n";

// At most 2 children run concurrently; additional go() calls suspend the
// caller until a slot frees up.
$taskId2 = new TaskId('group-limited');
$executionId2 = ExecutionId::fromString('group-limited-exec');

$limitedResults = $scheduler->dispatch(
    new Task(
        new TaskId('group-limited-task'),
        $executionId2,
        $fixture['rootScope'],
    ),
    static function () use ($scheduler, $taskId2, $executionId2, $fixture): array {
        $group = new TaskGroup(
            $taskId2,
            $executionId2,
            $fixture['rootScope'],
            $scheduler,
            maxConcurrency: 2,
            backpressurePolicy: BackpressurePolicy::Block,
        );

        $started = [];
        for ($i = 1; $i <= 4; $i++) {
            $index = $i;
            $group->go(static function () use ($scheduler, $index, &$started): string {
                $started[] = "start-$index";
                $scheduler->awaitDelay(Duration::ofNanoseconds(300_000));

                return "done-$index";
            });
        }

        $results = $group->await();

        return ['started' => $started, 'results' => $results];
    },
);

echo 'start order (max 2 concurrent): ';
echo implode(', ', $limitedResults['started']), "\n";
echo 'completion order: ';
echo implode(', ', $limitedResults['results']), "\n";

echo "\n=== Task lifecycle status ===\n";
$status = $runtime->run($scope, static function () use ($runtime): string {
    $task = $runtime->spawn(static function (): void {
    });

    return $task->status()->value;
});

echo 'completed task status: ', $status, "\n";
