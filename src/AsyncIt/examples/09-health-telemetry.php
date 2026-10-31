<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

use ItPack\RunIt\Domain\Shared\Duration;

$fixture = makeReferenceRuntime();
$runtime = $fixture['runtime'];
$scope = makeScope('health-telemetry');
$telemetry = $fixture['telemetry'];

echo "=== SchedulerHealth: runtime state snapshot ===\n";

// health() returns null before the first run() (no scheduler active yet).
echo '  health before run: ', $runtime->health() === null ? 'null' : 'snapshot', "\n";

// health() is only available while a scheduler is active (inside run()).
$runtime->run($scope, static function () use ($runtime): void {
    $runtime->spawn(static function () use ($runtime): void {
        $runtime->awaitDelay(Duration::ofNanoseconds(100_000));
    });

    $runtime->spawn(static function () use ($runtime): void {
        $runtime->awaitDelay(Duration::ofNanoseconds(200_000));
    });

    $health = $runtime->health();

    echo "  health during run:\n";
    echo '    isHealthy:       ', $health->isHealthy() ? 'true' : 'false', "\n";
    echo '    pendingTasks:    ', $health->pendingTaskCount(), "\n";
    echo '    activeHandles:   ', $health->activeHandleCount(), "\n";
    echo '    uptime:          ', $health->uptime()->nanoseconds(), " ns\n";
    echo '    recentErrors(5): ', count($health->recentErrors(5)), "\n";

    // Let tasks complete.
    $runtime->awaitDelay(Duration::ofNanoseconds(500_000));

    $health = $runtime->health();
    echo "  health after tasks complete:\n";
    echo '    isHealthy:       ', $health->isHealthy() ? 'true' : 'false', "\n";
    echo '    pendingTasks:    ', $health->pendingTaskCount(), "\n";
    echo '    activeHandles:   ', $health->activeHandleCount(), "\n";
});

echo "\n=== TelemetryBridge: recorded scheduler events ===\n";

// telemetry() returns the telemetry bridge. The default implementation uses
// ArrayTelemetryRecorder, which records every event for inspection.
echo '  telemetry events recorded: ', count($telemetry->events()), "\n";

foreach ($telemetry->events() as $i => $event) {
    echo "    [$i] ", $event['event'], "\n";
    if ($event['context'] !== []) {
        echo '        context: ', json_encode($event['context']), "\n";
    }
}

echo "\n=== TelemetryBridgeInterface: programmatic recording ===\n";

// The bridge can be used directly to record custom events from application code.
// In production, swap the LazyTelemetryBridge for an OpenTelemetry-backed
// implementation without changing consumer code.
$runtime->run($scope, static function () use ($runtime): void {
    $runtime->telemetry()->record('custom.event', [
        'task' => 'example-task',
        'outcome' => 'success',
    ]);
});

echo '  events after custom record: ', count($telemetry->events()), "\n";
$events = $telemetry->events();
$last = end($events);
echo '  last event: ', $last['event'], ' => ', json_encode($last['context']), "\n";
