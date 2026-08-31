# ADR 0008 — Context Provider `reset()` Semantics

- **Status:** Accepted (2026-08-29)
- **Supersedes:** —
- **Superseded by:** —
- **Related:** REVIEW.md F-17 (context retention), Milestone M9 (§9.3); ADR 0003 (fiber-bound context)

## Context

`CommandIt` isolates execution context per call stack via `ExecutionContextProviderInterface`
implementations: `AmbientContextStorage` (synchronous/main scope) and `FiberBoundContextStorage`
(per-Fiber, keyed in a `WeakMap`). In long-running, persistent-worker runtimes a provider must be
restored to a clean state between requests so ambient context never bleeds across dispatches.

The question is *what* `reset()` means and *where* it belongs:

1. **Scope.** A Fiber-bound provider holds many independent stacks (one per `Fiber`). A single
   `reset()` cannot address "all fibers" — the `WeakMap` is not iterable by design, and iterating
   live Fibers would be both unsafe and meaningless (a detached Fiber is collectable exactly
   because we cannot reach it). Reset therefore applies to **one** stack: the calling Fiber's, or
   the ambient (main) scope when no Fiber is current.
2. **Port surface.** `reset()` is an operational concern of the concrete adapter, not a capability
   of the domain's execution model. Adding it to `ExecutionContextProviderInterface` would imply
   every provider — including future RunIt adapters — must expose a reset, and would let consumers
   mistake it for a domain concept. It must stay **off** the port interface.
3. **Safety.** After a reset, any token still held from before the reset must fail loudly (not
   silently corrupt the new stack), so the contract's "exact token" guarantee is preserved.

## Decision

- Add `reset(): void` to the three concrete providers:
  - `ContextStack::reset()` — drops every entered context (`$items = []`).
  - `AmbientContextStorage::reset()` — delegates to its `ContextStack`.
  - `FiberBoundContextStorage::reset()` — resets the **calling** Fiber's `ContextStack` only; when
    `RuntimeContextDetectorInterface::currentFiber()` is `null` it resets the ambient provider. A
    Fiber whose stack does not exist is a no-op (nothing to clear).
- `reset()` is **not** added to `ExecutionContextProviderInterface`. It is documented as
  adapter-specific operational surface for the pre-1.0 window; if a future RunIt adapter needs it,
  the seam can be promoted then.
- A `leave()` with a pre-`reset()` token still throws `InvalidContextTokenException` (loud, not
  silent), because the stack is now empty and the token cannot match the top.

## Consequences

- Persistent-worker operators call `reset()` on the main (ambient) provider between requests; Fiber
  runtimes call it on the current Fiber's provider when that Fiber is recycled. There is no
  process-wide "clear everything" — that is intentional and documented.
- Consumers who previously relied on unbounded ambient buildup between dispatches must now reset
  explicitly; this is the desired behavior (F-17 adjacent: no silent retention).
- No public interface signature changed; the change is purely additive on concrete adapters.
