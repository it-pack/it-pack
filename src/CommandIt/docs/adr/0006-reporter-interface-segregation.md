# ADR 0006 — Reporter interface segregation & coordinator contract honesty

- **Status:** Accepted
- **Date:** 2026-08-29
- **Milestone:** M7 (REVIEW findings F-06, F-07)

## Context

SOLID analysis (REVIEW F-06, F-07) surfaced two contract-level lies in the core
surface while it is still cheap to change:

1. **Fat reporter port.** `ExecutionReporterInterface` bundled five methods —
   `reportObservation`, `reportOccurrence`, `reportMeasurement`, `beginActivity`,
   `endActivity`. The core pipeline (coordinator + executor) only ever calls
   `reportOccurrence` and `reportMeasurement`; observations and activity boundaries
   serve adapters and future model legs (Activities). Consumers that need only the
   lifecycle signal were forced to implement a fat interface — an ISP violation.
2. **Dishonest coordinator contract.** `CommandExecutionCoordinatorInterface` typed
   its artifacts as `ExecutionArtifactInterface`, a marker that carries no
   `transition()`/`complete()`/`result()` surface. The only implementation
   (`CommandExecutionCoordinator`) therefore downcast with `instanceof
   CommandExecution` + a `LogicException` on mismatch — a latent LSP violation that
   hid the real guarantee: the core pipeline always produces a concrete
   `CommandExecution`.

## Decision

- **Segregate the reporter port (ISP).** Introduce `LifecycleReporterInterface` with
  exactly `reportOccurrence(string $type, array $context = [])` and
  `reportMeasurement(string $name, int|float $value, string $unit)`. The stable
  occurrence/measurement vocabulary constants (`COMMAND_RECEIVED`/`HANDLED`/`FAILED`,
  `CONTEXT_*`, `MEASUREMENT_DURATION`, `DURATION_UNIT_MS`) move there.

  `ExecutionReporterInterface extends LifecycleReporterInterface`, keeping
  `reportObservation`, `beginActivity`, `endActivity`. Existing implementations and
  consumers compile unchanged (a full reporter is a structural superset), and the
  core now depends only on the narrow `LifecycleReporterInterface`.

- **Tell the truth in the coordinator contract (LSP).** `CommandExecutionCoordinatorInterface`
  signatures change from `ExecutionArtifactInterface` to the concrete `Domain\CommandExecution`.
  The `Contract` layer is deliberately permitted to depend on `Domain` in `deptrac.yaml`
  (`Contract` → `Domain`): the contract surface describes the concrete RunIt-shaped
  execution, and `Domain` already depends on `Contract` (the pairing is intentional — Domain
  remains independent of `Application`, `SDK`, adapters, and external standards, which is the
  binding AGENTS.md rule). The downcast helper and its `LogicException` are deleted; the
  polymorphic-artifact seam is dropped because the core pipeline always produces this
  RunIt-shaped record.

## Consequences

- A reporter implementing **only** `LifecycleReporterInterface` now drives a full
  dispatch through a hand-assembled executor (covered by
  `CommandExecutorTest::testCoordinatorDrivesFullDispatchWithNarrowLifecycleReporterOnly`).
- `CommandExecutionCoordinator` has zero `instanceof` downcasts; its constructor and
  property are typed `LifecycleReporterInterface`.
- **Breaking signature change** (pre-1.0 policy): any third-party coordinator or
  reporter double typed against `ExecutionArtifactInterface` must adopt `CommandExecution`.
  `CommandIt`/`CommandItFactory` keep wiring the full reporter, so the SDK surface is
  unchanged.
- Polymorphic artifacts (e.g. a different `ExecutionArtifactInterface` subtype) remain a
  *possibility* for a post-model leg and can be reintroduced deliberately, with the port
  re-segregated then; this ADR records that the current honesty is preferable to a lie
  that forced a runtime guard.

## Alternatives considered

- **Keep the fat reporter, narrow only at call sites.** Rejected: it leaves the ISP
  violation in the public contract and still forces narrow consumers to implement dead
  methods.
- **Keep `ExecutionArtifactInterface` and add a covariant return on the concrete
  coordinator.** Rejected: an interface returning a supertype while the implementation
  returns a subtype is legal but still hides the real guarantee and preserves the
  `instanceof` downcast in every alternative coordinator implementation.
