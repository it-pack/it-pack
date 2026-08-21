# ADR 0001 — RunIt integration via ports, not imports

- **Status:** Accepted
- **Date:** 2026-08-21
- **Milestone:** M8 (supersedes the standing rule in `AGENTS.md`)

## Context

CommandIt models command execution as a specialization of the It_Pack execution model
(`docs/EXECUTION.md`). The sibling `ItPack\RunIt` package is the future home of the ecosystem's
execution pipeline (reporter, context provider, storage, runtime detector). We must let CommandIt
feed RunIt without coupling the two at compile time, and without leaking RunIt types into the core
domain or SDK.

## Decision

- CommandIt exposes its external-effect seams **only** as interfaces under `src/Contract/Port`
  (`ExecutionReporterInterface`, `ExecutionContextProviderInterface`,
  `CommandExecutionStorageInterface`, `RuntimeContextDetectorInterface`, `ClockInterface`,
  `IdentifierGeneratorInterface`, `SerializerInterface`, `RedactionPolicyInterface`).
- No source under `src/` (Domain, Application, SDK, Adapter) may `use ItPack\RunIt\…`. DepTrac encodes
  `RunIt` as a layer that is forbidden from every allowed list, so any import is a hard violation.
- The concrete RunIt bridge ships as a **separate** package, `it-pack/command-it-run-it`, which
  implements the `Contract/Port` interfaces and translates CommandIt artifacts into RunIt's pipeline.
- `CommandExecution` is intentionally RunIt-shaped (id, status, `startedAt`/`endedAt`, `result`,
  occurrences) but self-contained; it does **not** extend or reference RunIt classes.
- Serialized/normalized fields are not altered to chase a guessed RunIt API. Any mismatch between
  CommandIt's artifact shape and RunIt's expected representation is absorbed inside the adapter.
- Occurrence types (`command.received`, `command.handled`, `command.failed`) are stable, lowercase,
  versioned strings independent of PHP class names, so they project cleanly onto PSR-14 / CloudEvents
  without coupling to those standards.

## Consequences

- CommandIt stays vendor- and runtime-neutral; it runs with the in-box `Null`/`Collecting`/`Ambient`
  adapters and needs no RunIt dependency.
- A future RunIt integration is a pure adapter package; it cannot break CommandIt's public API.
- The `ExecutionArtifactInterface` marker is the single RunIt-alignment seam and may later widen into
  a normalization contract without touching core.

## Alternatives considered

- **Direct dependency on RunIt types in core.** Rejected: violates the architecture boundaries in
  `AGENTS.md` and would make CommandIt unshippable without RunIt.
- **Shared "common" execution base package.** Rejected: premature coupling; the port set already
  provides the seam with looser coupling.
