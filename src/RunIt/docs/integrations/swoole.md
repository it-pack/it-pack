# RunIt + Swoole / OpenSwoole

RunIt supports Swoole and OpenSwoole coroutine runtimes so that persistent workers and HTTP
servers keep each request (or job) isolated. The `ItPack\RunIt\Adapter\Swoole` adapter is
vendor-neutral: it depends only on RunIt contracts and probes the runtime with `class_exists`,
so it loads and degrades gracefully even when neither extension is installed.

See `docs/adr/ADR-022-swoole-adapter-packaging.md` for the packaging decision. Minimum supported
versions are **Swoole >= 4.6** and **OpenSwoole >= 4.9**.

## Components

| Class | Role |
| --- | --- |
| `CoroutineContextStorage` | Coroutine-local scope storage keyed by coroutine id |
| `CoroutineIdProviderInterface` / `SwooleCoroutineIdProvider` | Reads the current coroutine id from Swoole or OpenSwoole |
| `SwooleRuntimeDetector` | Reports `inCoroutine()` / `inFiber()` for runtime selection |
| `ServerExecutionMiddleware` | Wraps each request in one RunIt execution |
| `WorkerLifecycleReporter` | Reports worker start/stop/failure as RunIt occurrences |

## Coroutine context storage

Each coroutine gets its own scope stack keyed by coroutine id. A scope attached in one coroutine
is invisible to every other coroutine and to the main stack, so sequential requests handled by a
persistent worker stay isolated. When a stack empties it is removed, so a later coroutine that
reuses the same id starts clean.

```php
use ItPack\RunIt\Adapter\Swoole\CoroutineContextStorage;
use ItPack\RunIt\Adapter\Swoole\SwooleCoroutineIdProvider;

$storage = new CoroutineContextStorage(new SwooleCoroutineIdProvider());
```

Pass `$storage` to `RunItBuilder::create()->withContextStorage($storage)` together with the
required ports before building the runtime. The builder's default `StackContextStorage` is
for synchronous nesting only: it does not isolate interleaved coroutines in a persistent worker,
and the builder does not automatically select coroutine-aware storage.

Combine it with Fiber and synchronous storage through `CompositeContextStorage` (see
`docs/integrations/fibers.md`) for a single runtime-aware storage.

## HTTP server middleware

`ServerExecutionMiddleware` wraps each request in one execution, delegates the work to your handler
(which receives the active `ExecutionScopeInterface`), records the request duration and a handled
observation, and relies on the runner's `finally` block to detach the scope and clear request-local
state. Unhandled throwables are recorded by the runner and rethrown.

```php
use ItPack\RunIt\Adapter\Swoole\Http\ServerExecutionMiddleware;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;

$middleware = new ServerExecutionMiddleware(
    $runner,
    ExecutionName::fromString('http.request'),
    ExecutionKind::fromString('http.request'),
    null,            // optional RequestContextExtractorInterface for incoming correlation
    $clock,          // optional MonotonicClockInterface for duration measurement
);

$response = $middleware->handle($request, function (mixed $request, ExecutionScopeInterface $scope) {
    return $handler($request, $scope);
});
```

## Worker lifecycle

`WorkerLifecycleReporter` reports worker start, stop, and failure as RunIt occurrences owned by a
dedicated worker-lifecycle execution — strictly separate from per-request executions. `reset()`
invokes the supplied reset and flush hooks, so it is safe to call from a worker reload handler.

```php
use ItPack\RunIt\Adapter\Swoole\Worker\WorkerLifecycleReporter;

$reporter = WorkerLifecycleReporter::create(
    $runner,
    resetHook: fn () => $container->reset(),
    flushHook: fn () => $publishers->flush(),
);

$reporter->start();
// ... serve requests ...
$reporter->stop();
```

## Persistent-worker requirements

- Run one execution per request and detach it in `finally` (the runner does this). Never retain the
  execution scope between requests.
- Do not capture process-global mutable state. Bind all per-request data to the `ExecutionScope`.
- Flush bounded publishers within the shutdown budget (pass a `flushHook` to the worker reporter).
- Reuse coroutine ids are safe: contexts are cleared on detach, so a reused id starts empty.

## Runtime limitations and unsupported combinations

- Fibers and coroutines are distinct PHP concurrency primitives. RunIt selects exactly one context
  owner per call (coroutine, Fiber, or synchronous stack); it does not nest a Fiber inside a
  coroutine or vice versa through the composite storage.
- The adapter never configures a global Swoole/OpenSwoole provider and imposes no retry or
  durable-delivery guarantee; delivery semantics are the consumer's responsibility.
- When neither extension is loaded, `SwooleRuntimeDetector::inCoroutine()` returns `false` and
  `CoroutineContextStorage` behaves like a main-stack storage, so the same code path is safe in a
  non-Swoole deployment.
