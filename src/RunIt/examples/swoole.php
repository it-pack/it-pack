<?php

declare(strict_types=1);

/**
 * RunIt + Swoole / OpenSwoole — executable example.
 *
 * Demonstrates coroutine-local context storage and the server request middleware. When the Swoole
 * extension is loaded the work runs inside a real coroutine; otherwise the example uses the
 * synchronous default for its remaining work.
 *
 * Run from the package root: `php examples/swoole.php`
 */

require __DIR__ . '/../vendor/autoload.php';

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Adapter\Swoole\CoroutineContextStorage;
use ItPack\RunIt\Adapter\Swoole\Http\ServerExecutionMiddleware;
use ItPack\RunIt\Adapter\Swoole\SwooleCoroutineIdProvider;
use ItPack\RunIt\Adapter\Swoole\Worker\WorkerLifecycleReporter;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

$builder = RunItBuilder::create()
    ->withPublisher(new InMemoryArtifactPublisher())
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator());

if (extension_loaded('swoole') || extension_loaded('openswoole')) {
    $builder = $builder->withContextStorage(new CoroutineContextStorage(new SwooleCoroutineIdProvider()));
}

$runIt = $builder->build();

$runner = $runIt->executionRunner();

if (extension_loaded('swoole') || extension_loaded('openswoole')) {
    \Swoole\Coroutine\run(static function () use ($runIt): void {
        $runIt->run(
            ExecutionDefinition::create(ExecutionName::fromString('coroutine.work'), ExecutionKind::fromString('coroutine')),
            static function (ExecutionScopeInterface $scope): void {
                echo 'coroutine execution: ' . $scope->execution()->id()->toString() . PHP_EOL;
            },
        );
    });
} else {
    echo 'Swoole/OpenSwoole not loaded; using synchronous context storage.' . PHP_EOL;
}

// Server request middleware: one execution per request, with duration and status recorded.
$middleware = new ServerExecutionMiddleware(
    $runner,
    ExecutionName::fromString('http.request'),
    ExecutionKind::fromString('http.request'),
    null,
    new SystemMonotonicClock(),
);

$response = new class {
    public function getStatusCode(): int
    {
        return 200;
    }
};

$result = $middleware->handle(new \stdClass(), static fn (mixed $request, ExecutionScopeInterface $scope) => $response);
echo 'middleware returned status: ' . $result->getStatusCode() . PHP_EOL;

// Worker lifecycle events are reported as their own executions.
$reporter = WorkerLifecycleReporter::create($runner);
$reporter->start();
$reporter->stop();
echo 'worker lifecycle reported' . PHP_EOL;
