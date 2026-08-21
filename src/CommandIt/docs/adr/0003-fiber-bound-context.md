# ADR 0003 — Fiber-bound execution context

- **Status:** Accepted
- **Date:** 2026-08-21
- **Milestone:** M8 (codifies the M3 decision)

## Context

Execution context (`CommandContext`) must be immutable per execution and must never leak between
concurrent requests in a persistent worker (Swoole, OpenSwoole, Fiber coroutines, or a CLI loop).
Process-global mutable state is explicitly forbidden by `AGENTS.md`. We need isolation that works for
both the main stack and coroutine runtimes without coupling to any specific runtime.

## Decision

- Context storage is supplied through `ExecutionContextProviderInterface` (a port). The default
  implementations live in `src/Adapter/Context`.
- `FiberBoundContextStorage` keeps per-Fiber context in a `WeakMap<\Fiber, CommandContext>` keyed by
  the `Fiber` object. It delegates the **main stack** (when `RuntimeContextDetectorInterface::
  currentFiber()` returns `null`) to `AmbientContextStorage`, which holds only the current request's
  context for the non-coroutine path.
- The runtime detector (`RuntimeContextDetectorInterface`) returns the active `Fiber` and reports
  whether a coroutine runtime is active; CommandIt never imports a concrete runtime.
- Context is always **copy-on-write**: `CommandContextInterface::with()` returns a new context and
  never mutates the receiver. The core executor restores/clear the prior context in `finally`, so a
  failed or cancelled command cannot poison the next one.
- `WeakMap` keying means context does not extend the lifetime of a finished Fiber and cannot cause a
  cross-request bleed.

## Consequences

- No global mutable execution state; isolation is structural, not convention-based.
- `PersistentWorkerIsolationTest` proves 50 sequential dispatches leak no context and concurrent
  Fibers keep isolated contexts.
- Adding a new coroutine runtime only requires a new `RuntimeContextDetectorInterface` adapter, not
  core changes.

## Alternatives considered

- **Global/request singleton context bag.** Rejected: leaks across persistent-worker requests and
  violates the security/isolation rules.
- **Per-thread storage.** Rejected: Fibers share a thread, so it would not isolate coroutines.
