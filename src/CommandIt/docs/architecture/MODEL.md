# CommandIt — Domain & Architecture Model

> This document is the canonical reference for CommandIt's domain artifacts and
> their mapping to the shared It_Pack execution model (`docs/EXECUTION.md`).
> It is filled in progressively as milestones land.

## Status

- **Milestone 0 (skeleton & quality gates)**: shipped.
- **Milestone 1 (value objects & behaviors)**: shipped.
- **Milestone 2 (domain model)**: shipped.
- **Milestone 3 (ports + default adapters)**: shipped — all external-effect contracts and RunIt-agnostic defaults in place (clock, ids, serializer, reporter, context storage, execution storage, runtime detector, lazy handler proxy).
- **Milestone 4 (application core / orchestration)**: shipped — the command bus + executor run the lifecycle (validate → received → executing → terminal), resolve handlers lazily, compose middleware, record occurrences via `CommandExecutionCoordinator`, and rethrow the original business exception on handler failure. `CommandExecutor` implements `CommandBusInterface` + `CommandExecutorInterface`.
- SDK, CLI adapter, cross-cutting, and release hardening follow in later milestones.

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
| Command | `Command` (port-dispatched via `CommandBusInterface`) |
| Handler | `CommandHandlerInterface` (resolved lazily by `HandlerResolver`) |
| Execution | `CommandExecution` (orchestrated by `CommandExecutor` + `CommandExecutionCoordinator`) |
| Context | `CommandContext` (port-provided, fiber-bound) |
| Activity | handler work (future) |
| Measurement | `duration_ms` (monotonic clock, reported by coordinator) |
| Observation | reporter observation |
| Occurrence | `CommandReceived`, `CommandHandled`, `CommandFailed` |
| Snapshot | `CommandResult` (carries the `CommandExecution`) |

See `docs/EXECUTION.md` for the governing principles.
