# CommandIt — Domain & Architecture Model

> This document is the canonical reference for CommandIt's domain artifacts and
> their mapping to the shared It_Pack execution model (`docs/EXECUTION.md`).
> It is filled in progressively as milestones land.

## Status

- **Milestone 0 (skeleton & quality gates)**: in progress.
- Domain model, ports, core orchestration, SDK, and CLI adapter follow in later milestones.

## Layering (DepTrac)

```text
Domain  → Contract, Common
Application (Core) → Domain, Contract, Port, Common
SDK → Application, Domain, Contract, Port, Common
Adapter → Domain, Contract, Port, Common
```

External adapters (Symfony Console, RunIt) are **forbidden** from every internal
layer. CommandIt must remain RunIt-agnostic; RunIt integration arrives later via
ports (`src/Contract/Port`) only.

## Vocabulary mapping

| Execution model | CommandIt artifact |
|---|---|
| Execution | `CommandExecution` |
| Context | `CommandContext` (port-provided) |
| Activity | handler work (future) |
| Measurement | duration/measurement (future) |
| Observation | reporter observation (future) |
| Occurrence | `CommandReceived`, `CommandHandled`, `CommandFailed` |
| Snapshot | `CommandResult` |

See `docs/EXECUTION.md` for the governing principles.
