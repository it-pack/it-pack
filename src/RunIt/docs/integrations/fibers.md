# RunIt + Fibers

RunIt execution context is ambient: the active `ExecutionScopeInterface` is resolved from a
`ContextStorageInterface`. The default `StackContextStorage` keeps one scope stack for the
synchronous call stack. When work runs inside PHP Fibers, each Fiber needs its own isolated
scope so concurrent Fibers never share — or leak — context.

The builder does not automatically detect interleaved execution or select Fiber storage.
Sharing its default stack across suspended Fibers can expose another Fiber's active execution
or cause out-of-order detach. Configure Fiber-aware storage explicitly, including in persistent
workers that process overlapping requests. A Fiber execution engine alone does not select storage.

The `ItPack\RunIt\Adapter\Fiber` adapter provides Fiber-aware storage and an optional Fiber
execution engine.

## Components

| Class | Role |
| --- | --- |
| `FiberContextStorage` | Per-Fiber (and main-stack) scope storage backed by a `WeakMap` keyed by the `Fiber` object |
| `FiberContextToken` | Opaque token pinning a scope to the Fiber that owns it |
| `FiberRuntimeDetector` | Reports `inFiber()` from `Fiber::getCurrent()`; never reports a coroutine |
| `FiberExecutionEngine` | Runs the consumer operation inside its own `Fiber` |

## Fiber context storage

`FiberContextStorage` gives every Fiber its own scope stack. A scope attached in one Fiber is
invisible to every other Fiber and to the main stack. Associations live in a `WeakMap` keyed by
the `Fiber` instance, so once a Fiber is detached and no longer referenced it becomes eligible
for collection without manual cleanup.

```php
use ItPack\RunIt\Adapter\Fiber\FiberContextStorage;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

$runIt = RunItBuilder::create()
    ->withContextStorage(new FiberContextStorage())
    ->withPublisher($publisher)
    ->withClock($clock)
    ->withSequenceAllocator($allocator)
    ->withSnapshotSerializer($serializer)
    ->withIdentifierGenerator($generator)
    ->build();

$fiber = new Fiber(function () use ($runIt): void {
    $runIt->run(ExecutionDefinition::create(
        ExecutionName::fromString('fiber.work'),
        ExecutionKind::fromString('fiber'),
    ), function (ExecutionScopeInterface $scope): void {
        // $scope is isolated to this Fiber; CurrentExecution resolves it correctly.
    });
});

$fiber->start();
```

## Composite (runtime-aware) storage

`CompositeContextStorage` (in `Application\Propagation`) selects the right storage per call:
coroutine → coroutine storage, Fiber → Fiber storage, otherwise the synchronous stack. The
token pins the owning storage so detachment always targets the correct sub-storage.

```php
use ItPack\RunIt\Adapter\Fiber\FiberContextStorage;
use ItPack\RunIt\Adapter\Swoole\CoroutineContextStorage;
use ItPack\RunIt\Adapter\Swoole\SwooleCoroutineIdProvider;
use ItPack\RunIt\Adapter\Swoole\SwooleRuntimeDetector;
use ItPack\RunIt\Application\Propagation\CompositeContextStorage;
use ItPack\RunIt\Application\Propagation\StackContextStorage;

$storage = new CompositeContextStorage(
    new StackContextStorage(),
    new FiberContextStorage(),
    new CoroutineContextStorage(new SwooleCoroutineIdProvider()),
    new SwooleRuntimeDetector(),
);
```

> The SDK builder intentionally does not construct these adapters (the SDK layer may not depend
> on adapters). Wire the instance through `RunItBuilder::create()->withContextStorage($storage)`
> along with the required ports before calling `build()`.

## Fiber execution engine

By default operations run synchronously (`SynchronousExecutionEngine`). A `FiberExecutionEngine`
runs the operation on its own Fiber call stack. It starts and resumes the Fiber itself; it is not
a general-purpose scheduler for operations that suspend. The runner attaches its scope before
invoking the engine, so Fiber-local ambient lookup inside the engine-created Fiber does not
automatically inherit the caller's scope. Use the explicit scope passed to the operation.

```php
use ItPack\RunIt\Adapter\Fiber\FiberExecutionEngine;

$runIt = RunItBuilder::create()
    ->withContextStorage(new FiberContextStorage())
    ->withExecutionEngine(new FiberExecutionEngine())
    // ... required ports
    ->build();
```

## Context cleanup

- A scope attached in a Fiber is detached by the runner's `finally` block when the execution
  ends, even on an exception, so the Fiber's stack is cleared.
- Completed Fibers are released by the `WeakMap` once no strong reference remains, so long-running
  workloads do not accumulate context.
- Never store request or execution context in process-global mutable state. Choose the storage
  appropriate to the runtime; the default shared stack is not Fiber- or coroutine-local.
