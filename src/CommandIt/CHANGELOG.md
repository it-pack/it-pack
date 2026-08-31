# Changelog

All notable changes to `it-pack/command-it` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
(0.x: breaking changes may occur between minor releases).

## [Unreleased]

Refactoring-plan milestones M1–M10. Before the `1.0.0` tag these are grouped under
`[Unreleased]` because the public surface is still stabilizing; each item preserves
backward compatibility unless explicitly noted.

### Added

- **Bounded in-memory storage** — `InMemoryCommandExecutionStorage` now keeps at most
  `DEFAULT_MAX_ENTRIES` (1000) executions and evicts the oldest on overflow
  (pass `0` for an unbounded store). Additive `clear(): void` and `count(): int`
  (M9 / F-17).
- **Context `reset()`** — additive `reset(): void` on `ContextStack`,
  `AmbientContextStorage`, and `FiberBoundContextStorage` (calling-fiber-only, by
  design). Intended for persistent-worker isolation between requests (M9 / ADR 0008).
- **Serializer cycle & depth guards** — `SafeSerializer` now throws
  `SerializationException::cyclic()` on repeated/self-referencing input and
  `SerializationException::tooDeep()` past `DEFAULT_MAX_DEPTH` (32, configurable).
- **`LifecycleReporterInterface`** split out of `ExecutionReporterInterface` so a
  reporter can subscribe to only lifecycle occurrences (M7 / ADR 0006).
- **`CommandExecution` coordinator** is now a concrete domain object assembled by the
  application coordinator instead of a synthetic array (M7 / ADR 0006).
- **Redaction exact-match mode** — `RedactionPolicy` accepts `$exactKeyMatch` to
  compare whole lowercased keys instead of substring fragments (M5).
- **IPv4/v6, URL, email, and enum input types** join the `InputType` coercion set,
  with unknown types rejected at definition time (M8 / F-08).

### Changed

- **CLI bridge diagnostics go to stderr** — Symfony Console adapter output and
  diagnostics are written to `php://stderr`; only the command result reaches the
  console (M2 / ADR 0004).
- **argv grammar tightened** — long options only (`--name`, `--name=value`,
  `--name value`); `--` terminates option parsing; negative numbers are accepted as
  positional arguments; short flags (`-f`) are rejected loudly (M2).
- **Registry throws on unknown command** — `CommandRegistry::definition()` throws
  `HandlerNotFoundException` instead of returning `null` (M2).
- **`SafeSerializer` is the default payload normalizer** — `CommandItFactory` wires
  it by default; result payloads are normalized before storage, best-effort (M7 / ADR 0007).
- **Transition matrix enforced** — `CommandExecution` status transitions follow the
  explicit `RECEIVED → EXECUTING → SUCCEEDED|FAILED` matrix; terminal states are
  immutable (M6 / ADR 0005).
- **Original throwable is always preserved** — a reporter or adapter failure never
  replaces the business exception (M1).
- **Stateless adapters are `final readonly`** — `SystemClock`, `RandomIdentifierGenerator`,
  `SafeSerializer`, `ArgvInputParser`, `ArrayInputAdapter`, `RedactionPolicy` (M10 / 10.1).

### Fixed

- **Summary truncation** — a safe/empty execution summary is no longer truncated to a
  minimum length; the normalization guard only bounds oversized summaries (M1).
- **DepTrac Symfony layer** — Symfony Console is correctly confined to the CLI adapter
  layer; the core never imports it (M3 / D1).
- **Fiber-unsafe recursion guard** — `GuardedExecutionReporter` keys its recursion
  depth by `Fiber`, so a delegate that drives another Fiber gets an independent depth
  and reaches the delegate instead of being false-routed to health (M9 / F-12).

### Parked (documented, not adopted)

- **Native lazy-object resolver** (`newLazyGhost`/`newLazyProxy`) — parked: the env
  cannot produce a reliable phpbench before/after table, and `LazyHandlerProxy`
  already covers lazy handler resolution via a closure factory. No measured win
  (M10 / 10.2).
- See `docs/archive/REFACTORING_PLAN.md` for the full parked-feature records
  (property-hook adoption, `CancellationPort`, sampling/routing stage, PSR adapters,
  RunIt package).

## [0.1.0] - Initial preview

- First tagged preview of the CommandIt command-bus and CLI-adapter surface.
- See `docs/archive/` for the historical roadmap, review, and implementation plan.
