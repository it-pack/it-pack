# ADR-024: Activity completion follows the same matrix discipline as executions

**Status:** Accepted

Activity lifecycle transitions now enforce a closed matrix identical in shape
to the execution matrix (ADR-023): `created` may move to `running` or
`cancelled`, `running` may move to any terminal status, and terminal statuses
never transition. The matrix lives on the domain
(`ActivityStatus::canTransitionTo()`), is declared on
`ActivityStatusInterface`, and is enforced structurally by
`Activity::complete()`.

Before this decision, activities were guarded only against *reopening* —
completing an already-terminal activity raised. Completing to a non-terminal
status was silently accepted, producing a record with `endedAt` set but a
`running` status: neither an open activity nor a completed one, violating the
spirit of "a completed activity cannot be reopened" by creating something that
is neither.

Consequences:

- `ActivityStatusInterface` gained `canTransitionTo()`. This is a breaking
  change for third-party status implementations (pre-1.0 deliberate; recorded
  in `docs/compatibility.md`), mirroring ADR-023.
- Completing a running activity to a non-terminal status raises
  `InvalidActivityTransitionException` (implements
  `LifecycleExceptionInterface`). Existing valid flows (`Succeeded`/`Failed`
  from `running`) are unaffected.
- Reopen protection via `ConflictingActivityCompletionException` is unchanged.
