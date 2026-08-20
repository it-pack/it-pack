# CommandIt — Domain & Architecture Model

> This document is the canonical reference for CommandIt's domain artifacts and
> their mapping to the shared It_Pack execution model (`docs/EXECUTION.md`).
> It is filled in progressively as milestones land.

## Status

- **Milestone 0 (skeleton & quality gates)**: shipped.
- **Milestone 1 (value objects & behaviors)**: shipped.
- **Milestone 2 (domain model)**: shipped.
- **Milestone 3 (ports + default adapters)**: shipped — all external-effect contracts and RunIt-agnostic defaults in place (clock, ids, serializer, reporter, context storage, execution storage, runtime detector, lazy handler proxy).
- Application core, SDK, CLI adapter, cross-cutting, and release hardening follow in later milestones.

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
