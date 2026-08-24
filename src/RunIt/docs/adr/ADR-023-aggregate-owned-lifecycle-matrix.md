# ADR-023: The execution lifecycle matrix is owned by the aggregate

**Status:** Accepted

The closed transition matrix for the execution lifecycle
(`created → running | cancelled`, `running → terminal`) is owned by the domain:
`ExecutionStatus::canTransitionTo()` is the single source of truth, and the
`Execution` aggregate enforces it structurally in `complete()`. The
application-level `ExecutionLifecycleCoordinator` applies the same matrix above
the aggregate and adds idempotent-completion semantics: a terminal execution
completed again with the same status is a no-op, while a repeat with a
different terminal status raises a conflicting-completion error.

Before this decision the matrix lived only in the coordinator. That left a gap:
any caller holding an execution record could bypass the coordinator and drive a
terminal record to a different outcome, because `Execution::complete()` rebuilt
the record with whatever status it was handed. AGENTS.md states the invariant
absolutely — *an execution has at most one terminal outcome* and *terminal
executions cannot transition again* — so enforcement now lives with the state
it protects.

Consequences:

- The aggregate rejects any re-completion of a terminal record, including a
  same-status repeat. Idempotency remains a documented coordinator behavior,
  not an aggregate one: the coordinator intercepts repeats before the aggregate
  is consulted.
- `ExecutionStatusInterface` gained `canTransitionTo()`. This is a breaking
  change for third-party status implementations (acceptable pre-1.0 and
  deliberate; recorded in `docs/compatibility.md`). Custom statuses must
  express the closed matrix instead of leaving transitions undefined.
- `ExecutionFailure::of()` validates that its status is terminal and its
  constructor became private, mirroring `ExecutionSuccess`, so a failure result
  can never carry a non-terminal status.
- Activities gained the matching reopen guard: completing a terminal activity
  raises `ConflictingActivityCompletionException`. A completed activity cannot
  be reopened.
- Lifecycle violations (`ConflictingCompletionException`,
  `InvalidLifecycleTransitionException`,
  `ConflictingActivityCompletionException`) implement
  `LifecycleExceptionInterface` so consumers can catch the documented marker;
  they also keep implementing `InvalidValueExceptionInterface` for backward
  compatibility with existing catch blocks.
