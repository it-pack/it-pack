# Tutorial 8 — Context ownership & storage

**Goal:** choose the right execution context storage for your concurrency model,
and understand why `id()` on a context token is not enough to prove ownership.

## The problem

RunIt stores the ambient execution scope so recorders can bind artifacts to the
right execution. The storage choice depends on your concurrency model:

| Runtime | Storage | Safe for concurrent work? |
|---|---|---|
| Synchronous (default) | `StackContextStorage` | No — single shared LIFO stack |
| PHP Fiber | `FiberContextStorage` | Yes — per-fiber isolation |
| Swoole/OpenSwoole coroutine | `CoroutineContextStorage` | Yes — per-coroutine isolation |
| Mixed runtimes | `CompositeContextStorage` | Yes — delegates by runtime detector |

`StackContextStorage` is the default, and it is correct for ordinary synchronous
PHP. But it is **not** an isolation boundary for interleaved Fibers or coroutines.
`RunItBuilder::build()` rejects pairing it with an engine that declares
`concurrent: true`.

## Synchronous — StackContextStorage

The default. Attaching returns a token; detaching requires that exact token in
reverse LIFO order. Nested executions restore the parent scope automatically:

```php
use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

$runtime = RunItBuilder::create()
    ->withPublisher(new InMemoryArtifactPublisher())
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->build()
;
```

No `withContextStorage()` is needed — `StackContextStorage` is the default.

> **Run this:** `examples/context-ownership-stack.php`

## Fiber — FiberContextStorage

Each Fiber (and the main stack) keeps its own scope stack. A scope attached in
one Fiber is invisible to every other Fiber and to the main stack:

```php
use ItPack\RunIt\Adapter\Fiber\FiberContextStorage;
use ItPack\RunIt\Adapter\Fiber\FiberExecutionEngine;

$runtime = RunItBuilder::create()
    ->withPublisher(new InMemoryArtifactPublisher())
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->withContextStorage(new FiberContextStorage())
    ->withExecutionEngine(new FiberExecutionEngine())
    ->build()
;
```

> **Run this:** `examples/context-ownership-fiber.php`

## Composite — CompositeContextStorage

When your application mixes synchronous code, Fibers, and coroutines, wrap the
storages together. Composite delegates to the active sub-storage based on a
runtime detector:

```php
use ItPack\RunIt\Application\Propagation\CompositeContextStorage;
use ItPack\RunIt\Application\Propagation\StackContextStorage;
use ItPack\RunIt\Contract\Port\RuntimeContextDetectorInterface;

final class MainStackDetector implements RuntimeContextDetectorInterface
{
    public function inCoroutine(): bool
    {
        return \extension_loaded('swoole') || \extension_loaded('openswoole');
    }

    public function inFiber(): bool
    {
        return \Fiber::getCurrent() !== null;
    }
}

$storage = new CompositeContextStorage(
    new StackContextStorage(),
    new FiberContextStorage(),
    new StackContextStorage(),
    new MainStackDetector(),
);

$runtime = RunItBuilder::create()
    ->withPublisher(new InMemoryArtifactPublisher())
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->withContextStorage($storage)
    ->build()
;
```

> **Run this:** `examples/context-ownership-composite.php`

## Token ownership: id() is not enough

`ContextTokenInterface::id()` returns a storage-defined integer. Two tokens from
different storage instances can share the same `id()`. Only the exact token
instance returned by `attach()` authorizes `detach()`:

```php
use ItPack\RunIt\Application\Propagation\StackContextStorage;

$storageA = new StackContextStorage();
$storageB = new StackContextStorage();

// Tokens from different storages can share the same id():
//   tokenA->id() === tokenB->id()  // may be true, coincidental

// But only the exact token detaches its scope:
$storageA->detach($tokenB); // throws InvalidContextTokenException
$storageA->detach($tokenA); // OK
```

> **Run this:** `examples/context-token-ownership.php`

## What you learned

- Match storage to your concurrency model: stack for sync, fiber for Fibers,
  coroutine for Swoole/OpenSwoole, composite for mixed.
- Never pair `StackContextStorage` with an engine reporting `concurrent: true`.
- Detach requires the exact token reference — `id()` alone is untrusted.

## Next

[Tutorial 9 — Concurrency validation boundary](09-concurrency-validation-boundary.md): the
opt-in validator seam for custom concurrency rules.
