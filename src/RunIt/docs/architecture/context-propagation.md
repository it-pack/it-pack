# RunIt Context Propagation

Execution context is the immutable, classified set of values an execution carries.
Propagation is explicit and adapter-backed: the runtime stores the active
execution and its context in a port, and ambient access goes through a small,
deliberately non-global adapter.

## Immutable context

`ExecutionContext` is immutable and copy-on-write: `with()` and `without()`
return changed copies and never mutate the receiver. Every entry is classified
(`public`, `internal`, `confidential`, `restricted`, `secret`) and carries an
optional expiry and source. Classification drives export, propagation, and
serialization independently (see `ContextClassification`). Secret values are
denied everywhere; restricted values are masked on redaction.

Context capacity is bounded (`MAX_ENTRIES`) and merging uses an explicit
`ContextCollisionPolicy` (`Replace`, `Keep`, `Throw`).

## Storage and the ambient scope

Context storage is a synchronous stack (`StackContextStorage`) keyed by an
immutable `ContextToken`. Attaching a scope pushes a token; detaching pops it and
restores the previous nested scope. The storage detects invalid tokens and
double detachment. This keeps execution state in explicit, per-call storage
rather than process-global mutable state, which is required for persistent-worker
and Fiber safety.

`CurrentExecution` is the ambient accessor. When no execution is active it
returns `null` (the null-current-execution behavior); it never reads or writes
global mutable state. Swoole/Fiber adapters store the current execution per
coroutine or Fiber and must clear or restore it in `finally`.

## Scope lifetime

The `ExecutionScope` handed to a consumer operation exposes the running
execution, its immutable context, the activity runner, and the measurement,
observation, occurrence, and snapshot recorders. Recorders bind every artifact
to the execution (and to the active activity, when one is running)
automatically, so the consumer never supplies ownership or timing.

The active activity identity changes as activities start and complete; open
activities are tracked so the completion policy can resolve them when the
execution finishes.

## Propagation diagrams

The same immutable context and the same `CurrentExecution` accessor behave
differently per runtime. The diagrams below show how the active execution and its
context are stored and restored in each model.

### Synchronous

A single call stack uses the synchronous `StackContextStorage`. Attaching a scope
pushes a `ContextToken`; detaching pops it and restores the previous nested scope.

```mermaid
flowchart TD;
    Caller["Caller"] --> Push["StackContextStorage.attach(token)"];
    Push --> Op["Consumer operation<br/>(ExecutionScope active)"];
    Op --> Nested["Nested call attaches child token"];
    Nested --> Pop2["detach → restore parent scope"];
    Pop2 --> Pop1["detach → restore prior scope"];
```

### Fiber

Each Fiber keeps its own context slot. The runtime adapter suspends the current
Fiber's context and restores the target Fiber's on switch, so no Fiber sees
another's execution state.

```mermaid
flowchart TD;
    Sched["Fiber scheduler"] --> F1["Fiber A → ContextStorage.suspend()"];
    Sched --> F2["Fiber B → ContextStorage.restore()"];
    F1 --> Slot["per-Fiber context slot"];
    F2 --> Slot;
```

### Coroutine (Swoole / OpenSwoole)

In a persistent worker, the `RuntimeContextDetectorInterface` resolves the active
coroutine and the adapter stores the current execution per coroutine. Context is
cleared or restored in `finally` so it never leaks between requests.

```mermaid
flowchart TD;
    Worker["Swoole / OpenSwoole worker"] --> C1["Coroutine 1 → RuntimeContextDetector"];
    Worker --> C2["Coroutine 2 → RuntimeContextDetector"];
    C1 --> Slot["coroutine-local current execution"];
    C2 --> Slot;
```

## Security posture

Propagation must not leak secrets or high-cardinality values. Context,
observations, occurrences, and snapshots are treated as potential exfiltration
paths: classification, redaction, maximum size, cardinality, and serialization
safety are applied before any external publication. Retaining context between
persistent-worker requests is prohibited.

## Vocabulary

- `run-it#ExecutionContext` — the immutable, classified context.
- `run-it#ContextClassification` — the classification vocabulary.

Related decisions: ADR-004 (explicit context propagation is canonical),
ADR-005 (ambient context is adapter-backed), ADR-012 (sensitive values are
deny-by-default). See `docs/EXECUTION.md` and `docs/concepts/context.md`.
