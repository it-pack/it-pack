# ADR 0005 — Execution transition matrix

- **Status:** Accepted
- **Date:** 2026-08-29
- **Milestone:** M6 (REVIEW §14 P2 items 13–15, finding F-16, decision D4)

## Context

`CommandExecution` represented its lifecycle as a closed `CommandStatus` enum but
the transition logic was only partially enforced: any non-terminal status could
move to any other non-terminal status (the executor's `transition()` merely
blocked terminal re-entry), and `complete()` accepted any non-terminal execution.
The contract docblock claimed "the executor enforces the transition matrix" while
the *aggregate* was permissive, so the only real guarantee was "terminal stays
terminal." Illegal predecessor pairs — `defined → executing`, `received → received`,
completing from `defined` — were silently representable, deferring the contradiction
to whatever consumer reasoned about the state machine.

REVIEW §8.2 further flagged that the public constructor allowed terminal executions
constructed without a result and result-bearing executions constructed without a
terminal status, so invalid states were directly constructible.

## Decision

- **Enforce the matrix (D4 option (a)).** `CommandExecution` owns the legal
  predecessor map as a private `ALLOWED_TRANSITIONS` constant:

  ```text
  defined   → received
  received  → executing
  executing → (terminal only, via complete())
  ```

  `transition()` consults the map and throws `CommandExecutionException` with both
  statuses interpolated when the move is illegal; a terminal execution still cannot
  transition at all. `complete()` now requires the current status to be `executing`
  (the only legal predecessor of a terminal outcome) before producing `succeeded`
  or `failed`.

- **Invalid states unrepresentable.** The constructor becomes `private`; the only
  public way to obtain a `CommandExecution` is `define()` (always `defined`), and
  all onward states are produced exclusively by `transition()`/`complete()`, which
  already preserve the terminal-with-result invariant. `complete()` additionally
  rejects `endedAt < startedAt` (`CommandExecutionException: Execution ended before
  it started.`).

- `cancelled` keeps no incoming edge: it is declared for the closed lifecycle but
  remains unreachable until a `CancellationPort` is introduced (see `CommandStatus`).

## Consequences

- Illegal transitions are impossible by construction; the aggregate now matches the
  AGENTS.md lifecycle diagram, not just the terminal guard.
- Custom coordinators that drive unusual sequences must follow the machine. The
  shipped `CommandExecutionCoordinator` already conforms (`begin`: defined→received;
  `succeed`/`fail`: received→executing→terminal).
- A behavior change for any caller that previously completed a `received`/`defined`
  execution directly: that now throws. No such call existed in `src/` or the tests.
- Timing invariants are checked once, at the only place that can produce a terminal
  copy, instead of being trusted downstream.

## Alternatives considered

- **Keep the aggregate permissive, document the matrix (D4 option (b)).** Rejected:
  the docblock already lied about enforcement, and a deferred contradiction is exactly
  the class of bug this milestone closes (REVIEW §14 P2).
- **Validate the matrix in the coordinator only.** Rejected: the invariant belongs to
  the execution artifact itself; an adapter should not be the sole guard against
  constructing a nonsensical status.
