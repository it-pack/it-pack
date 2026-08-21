# ADR 0002 — Lazy handler resolution

- **Status:** Accepted
- **Date:** 2026-08-21
- **Milestone:** M8 (codifies the M3/M4 decision)

## Context

Handlers can be expensive to construct (DB connections, HTTP clients, container pulls). CommandIt
must avoid constructing a handler for a command that is never executed, and must never pay that cost
during validation or for unregistered commands. At the same time the resolver must remain testable,
framework-free, and pluggable for DI-aware strategies.

## Decision

- `HandlerResolverInterface` returns a `CommandHandlerInterface` for a given command name and throws
  `HandlerNotFoundException` when no handler is registered.
- The default resolver (`Application/HandlerResolver`) wraps each handler behind a
  `LazyHandlerProxy` (M3.10). The proxy holds a **factory closure** and uses
  `GhostObject::newLazyGhost` so the real handler is instantiated only on the first `handle()` call.
- Construction happens at most once per dispatch and is skipped entirely when:
  - the command fails validation (rejected before resolution), or
  - no handler is registered for the command name (`HandlerNotFoundException` thrown before any
    construction).
- `LazyHandlerProxy` lives in `src/Application` (not `Adapter`) because deptrac forbids
  `Application → Adapter` and the default resolver (an Application component) depends on it.

## Consequences

- Hot-path dispatch never eagerly builds unused handlers (verified by `LazyHandlerResolutionTest`
  and the `benchHandlerResolution` phpbench subject).
- The resolver is a single narrow interface; consumers can supply a DI-aware strategy by implementing
  `HandlerResolverInterface` without touching the executor.

## Alternatives considered

- **Eager resolution in the registry.** Rejected: pays construction cost even for invalid/unmatched
  commands; contradicts the performance requirements in `AGENTS.md`.
- **Service-locator pull inside the executor.** Rejected: hidden container access is forbidden.
