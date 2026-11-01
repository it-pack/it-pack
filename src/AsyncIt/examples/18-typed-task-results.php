<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/bootstrap.php';


$fixture = makeReferenceRuntime();
$scheduler = $fixture['scheduler'];

echo "=== Typed Task Results (V.11) ===\n";

// V.11.1 / V.11.2 — spawn a succeeding task and inspect its typed outcome.
$successTask = $scheduler->spawn(static fn (): string => 'hello result');
$scheduler->run();

$successResult = $scheduler->resultTyped($successTask);
echo '  success isSuccess: ', $successResult->isSuccess() ? 'true' : 'false', "\n";
echo '  success isFailure: ', $successResult->isFailure() ? 'true' : 'false', "\n";
echo '  success value: ', $successResult->value(), "\n";
echo '  success error: ', $successResult->error() === null ? 'null' : 'present', "\n";
echo '  success status: ', $successResult->status()->value, "\n";
echo '  success duration (ns): ', $successResult->duration()->nanoseconds(), "\n";

// Spawn a failing task and inspect its typed outcome.
$failureTask = $scheduler->spawn(static function (): never {
    throw new RuntimeException('task failed');
});
try {
    $scheduler->run();
} catch (RuntimeException) {
    // Expected — the task failed; we want its terminal typed outcome.
}

$failureResult = $scheduler->resultTyped($failureTask);
echo '  failure isSuccess: ', $failureResult->isSuccess() ? 'true' : 'false', "\n";
echo '  failure isFailure: ', $failureResult->isFailure() ? 'true' : 'false', "\n";
echo '  failure value: ', $failureResult->value() === null ? 'null' : 'present', "\n";
echo '  failure error: ', $failureResult->error()?->getMessage() ?? 'null', "\n";
echo '  failure status: ', $failureResult->status()->value, "\n";

echo "=== Typed task results demonstrated ===\n";
echo "  - resultTyped() returns TaskResult with isSuccess()/isFailure()\n";
echo "  - value() holds the success value; error() holds the terminal throwable\n";
echo "  - status() reports the terminal TaskStatus\n";
echo "  - duration() reports monotonic elapsed time\n";
