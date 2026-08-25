# ADR 0004 — Command name collision policy

- **Status:** Accepted
- **Date:** 2026-08-25
- **Milestone:** M2 (REVIEW finding F-05, decision D2)

## Context

`CommandRegistry::register()` keyed entries by command name with plain array
assignment, so registering a second definition under an existing name silently
overwrote the first (last-wins). Nothing in the type system or tests signaled
the policy. The registry feeds both programmatic dispatch and the CLI projection
(`ConsoleCommandRegistry` → Symfony Console), where a collision is almost always
a wiring mistake — two assemblies registering the same `cache:clear`, for
example — and a silent overwrite makes the wrong handler win with no diagnostic.

## Decision

- Duplicate registration **fails fast**: `CommandRegistry::register()` throws
  `InvalidCommandException::duplicateCommand($name)` when the name is already
  present.
- The error carries the colliding name so the caller can identify the wiring
  conflict immediately.
- This is a deliberate pre-1.0 behavior change; no deprecation shim is provided.
- A replacement/override API (`registerOrReplace()`) may be added later if a
  concrete consumer needs swap semantics — not speculatively.

## Consequences

- Collisions surface at registration time instead of producing confusing
  runtime behavior in CLI or bus dispatch.
- Consumers that intentionally re-register must check `has()` first or catch
  `InvalidCommandException`; the exception hierarchy makes this precise.
- `ConsoleCommandRegistry::toConsoleCommands()` remains 1:1 with the registry,
  so the CLI can no longer inherit a hidden overwrite.

## Alternatives considered

- **Document last-wins as the contract.** Rejected: silent surprise on a path
  that feeds user-facing tooling; contradicts the fail-loud input-validation
  posture of the library.
- **Dedicated `replace()` method now.** Rejected until a consumer exists
  (AGENTS.md: do not add public extension points without concrete demand).
