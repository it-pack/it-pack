# ADR-022: Swoole/OpenSwoole adapter packaging

**Status:** Accepted

**Context**

Milestone M11 adds context-safe execution for concurrent and persistent PHP runtimes.
Swoole and OpenSwoole are the two maintained coroutine runtimes for PHP. ADR-020 placed
the OpenTelemetry bridge in the root package, and ADR-021 kept the CloudEvents bridge in
the root package; both followed the in-tree adapter pattern established by the PSR adapters
(M8). The Swoole integration is, like those, a vendor-neutral adapter that implements RunIt
ports without changing domain semantics.

Two questions must be resolved: (1) whether Swoole and OpenSwoole are one adapter or two,
and (2) whether the adapter lives in the root package or a dedicated bridge package.

**Decision**

A single adapter, `ItPack\RunIt\Adapter\Swoole`, serves both Swoole and OpenSwoole. The two
extensions expose the same coroutine API (`\Swoole\Coroutine::getCid()` and
`\OpenSwoole\Coroutine::getCid()`), so one `CoroutineContextStorage`, one
`SwooleRuntimeDetector`, and one `SwooleCoroutineIdProvider` cover both. The extension is
**never a hard dependency**: the provider and detector probe for either class with
`class_exists` and degrade to "not in a coroutine" when neither is loaded. Both extensions
remain under `suggest` in `composer.json` (`ext-swoole`, `ext-openswoole`).

The adapter stays in the root `it-pack/run-it` package, mirroring M8/M9/M10. The
CloudEvents and Swoole adapters both satisfy ADR-016's in-root criteria (vendor-neutral, no
non-core library dependency, same release cadence as core) and do not meet its split criteria.

Supported minimums are **Swoole >= 4.6** and **OpenSwoole >= 4.9** (the first releases with
the stable `\*\Coroutine::getCid()` surface used here). Consumers on older builds should pin
an earlier RunIt or avoid the coroutine storage.

The SDK layer must not depend on the adapter (deptrac forbids `SDK → Adapter`), so the
builder exposes `withExecutionEngine(ExecutionEngineInterface $engine)` (contract-based) but
does not construct Fiber or coroutine storage itself. Consumers wire `FiberContextStorage`,
`CoroutineContextStorage`, and `CompositeContextStorage` through `withContextStorage(...)`.
This matches how the OpenTelemetry and CloudEvents bridges are composed by consumers.

**Consequences**

- `src/Adapter/Swoole` depends only on `Contract`, `Domain`, and `Common`; it must not import
  `ext-swoole`/`ext-openswoole` types at class-load time (use `class_exists` guards).
- `CoroutineContextStorage` and `SwooleRuntimeDetector` must keep working when the extension
  is absent: `inCoroutine()` returns `false` and the storage routes through its main key.
- The Fiber adapter (`src/Adapter/Fiber`) is independent of Swoole and is always available.
- Context cleanup in coroutines relies on the execution runner detaching the scope in a
  `finally` block; the adapter does not hook coroutine destruction. Sequential requests in a
  persistent worker are isolated because each request runs its own execution and detaches.
- Unsupported combinations are documented in `docs/integrations/swoole.md`: Fibers and
  coroutines are distinct PHP concurrency primitives and are not nested by RunIt; the
  composite storage selects exactly one context owner per call.
