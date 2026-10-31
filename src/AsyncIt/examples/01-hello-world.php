<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

$fixture = makeReferenceRuntime();
$runtime = $fixture['runtime'];
$scope = makeScope('hello-world');

/**
 * The simplest possible AsyncIt program: run a single operation inside the
 * reference reactor and return its value. The operation executes in a Fiber;
 * the RunIt execution scope is attached on resume and detached under `finally`
 * on every suspension (conformance Assertion A).
 */
$result = $runtime->run($scope, static function (): string {
    return 'Hello from AsyncIt';
});

echo $result, "\n";
