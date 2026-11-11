<?php

declare(strict_types=1);

use ItPack\RunIt\Adapter\Fiber\FiberContextStorage;
use ItPack\RunIt\Adapter\Swoole\CoroutineContextStorage;
use ItPack\RunIt\Adapter\Swoole\SwooleCoroutineIdProvider;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Domain\Context\ExecutionContext;
use ItPack\RunIt\Domain\Execution\Execution;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Shared\Instant;
use Tests\Support\Fixture\RunItTestKit;

require dirname(__DIR__) . '/vendor/autoload.php';

$scope = new RunItTestKit()->buildScope(
    Execution::start(
        ExecutionId::fromString('bench-context'),
        ExecutionDefinition::create(ExecutionName::fromString('operation'), ExecutionKind::fromString('task')),
        Instant::of(1),
    ),
    ExecutionContext::empty(),
);

$measure = static function (string $runtime, ContextStorageInterface $storage) use ($scope): void {
    foreach ([0, 63] as $depth) {
        $parents = [];
        for ($i = 0; $i < $depth; ++$i) {
            $parents[] = $storage->attach($scope);
        }
        foreach (['lookup', 'pair'] as $operation) {
            $samples = [];
            for ($round = 0; $round < 12; ++$round) {
                $start = hrtime(true);
                for ($i = 0; $i < 10000; ++$i) {
                    if ($operation === 'lookup') {
                        $result = $storage->current();
                    } else {
                        $token = $storage->attach($scope);
                        $storage->detach($token);
                    }
                }
                $elapsed = (hrtime(true) - $start) / 10000 / 1000;
                if ($operation === 'lookup' && $result !== ($depth === 0 ? null : $scope)) {
                    throw new RuntimeException('Unexpected lookup result.');
                }
                if ($round >= 2) {
                    $samples[] = $elapsed;
                }
            }
            sort($samples);
            printf(
                "%s %s depth=%d median=%.6f range=%.6f-%.6f us/op\n",
                $runtime,
                $operation,
                $depth,
                ($samples[4] + $samples[5]) / 2,
                $samples[0],
                $samples[9],
            );
        }
        if ($storage->current() !== ($depth === 0 ? null : $scope)) {
            throw new RuntimeException('Context not restored.');
        }
        while ($parents !== []) {
            $storage->detach(array_pop($parents));
        }
        if ($storage->current() !== null) {
            throw new RuntimeException('Context not empty after cleanup.');
        }
    }
};

if (class_exists(Fiber::class)) {
    $fiber = new Fiber(static function () use ($measure): void {
        $measure('Fiber', new FiberContextStorage());
    });
    $fiber->start();
    if (!$fiber->isTerminated()) {
        throw new RuntimeException('Fiber benchmark did not finish.');
    }
} else {
    fwrite(STDERR, "SKIP Fiber: runtime unavailable\n");
}

$run = function_exists('Swoole\\Coroutine\\run') ? 'Swoole\\Coroutine\\run'
    : (function_exists('OpenSwoole\\Coroutine\\run') ? 'OpenSwoole\\Coroutine\\run' : null);
if ($run === null) {
    fwrite(STDERR, "SKIP coroutine: Swoole/OpenSwoole runtime unavailable\n");
} else {
    $completed = false;
    $run(static function () use ($measure, &$completed): void {
        $provider = new SwooleCoroutineIdProvider();
        if ($provider->currentCoroutineId() < 0) {
            throw new RuntimeException('Coroutine benchmark did not enter a coroutine.');
        }
        $measure('Coroutine', new CoroutineContextStorage($provider));
        $completed = true;
    });
    if (!$completed) {
        throw new RuntimeException('Coroutine benchmark did not finish.');
    }
}
