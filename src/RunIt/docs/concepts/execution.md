# Execution

An **execution** is one logical runtime operation. It is the root aggregate of the
RunIt domain: every context, activity, measurement, observation, occurrence, and
snapshot belongs to exactly one execution. The concept is defined by
[`runit:Execution`](https://w3id.org/itpack/runit#Execution) in the canonical
vocabulary; the narrative model is described in [`EXECUTION.md`](../EXECUTION.md).

> Everything happens within an execution.

## What an execution represents

An execution is a single unit of work from the perspective of a consumer. It is
not a trace, a transaction, or an HTTP request — those are concrete examples of
what an execution *can* model, but the domain term stays vendor-neutral.

Examples:

```
HTTP request        CLI command         Queue job
Configuration resolution  Workflow        Background worker
Scheduler tick      Cron job            Build process        Migration
```

## Types

| Contract | Value object | Notes |
|---|---|---|
| `ExecutionInterface` | `ItPack\RunIt\Domain\Execution\Execution` | Root aggregate; immutable once built |
| `ExecutionDefinitionInterface` | `ItPack\RunIt\Domain\Execution\ExecutionDefinition` | Immutable name, kind, parent/linked ids, context |
| `ExecutionIdInterface` | `ItPack\RunIt\Domain\Execution\ExecutionId` | Opaque stable identifier |
| `ExecutionNameInterface` | `ItPack\RunIt\Domain\Execution\ExecutionName` | Validated, extensible name |
| `ExecutionKindInterface` | `ItPack\RunIt\Domain\Execution\ExecutionKind` | Validated, extensible kind |
| `ExecutionStatusInterface` | `ItPack\RunIt\Domain\Execution\ExecutionStatus` | Closed lifecycle enum |
| `ExecutionResultInterface` | `ItPack\RunIt\Domain\Execution\ExecutionSuccess` / `ExecutionFailure` | Terminal outcome with optional throwable |
| `CancellationInterface` | `ItPack\RunIt\Domain\Execution\Cancellation` | Cancellation detail |
| `TimeoutInterface` | `ItPack\RunIt\Domain\Execution\Timeout` | Timeout detail |

## Lifecycle

An execution moves through a validated state machine (see
[`docs/architecture/lifecycle.md`](../architecture/lifecycle.md)):

```
CREATED
   │
   ▼
RUNNING
   ├──► SUCCEEDED
   ├──► FAILED
   ├──► CANCELLED
   └──► TIMED_OUT
```

`ExecutionStatus` carries the closed set `created`, `running`, `succeeded`,
`failed`, `cancelled`, `timed_out`, exposes `label()` for normalization, and
`isTerminal()` to guard transitions. A terminal execution cannot transition
again.

## Identity, ownership, and relationships

- **Identity.** Each execution has one opaque `ExecutionId`. Identifiers are
  generated through the `IdentifierGeneratorInterface` port and compared by type
  and value.
- **Context.** The execution carries one immutable `ExecutionContext` (see
  [`docs/concepts/context.md`](context.md)). Context lives on the immutable
  `ExecutionDefinition`; changing context produces a new definition, never a
  mutation of the live execution.
- **Nesting.** An execution may be nested through `parentExecutionId()` and
  linked through `linkedExecutionIds()`. Ownership is expressed by identity and
  relationships, not by a retained in-memory collection of children.
- **Artifacts.** Activities, measurements, observations, occurrences, and
  snapshots all report the owning `executionId()`.

## Normalization

`Execution::normalize()` returns a deterministic, recursively-primitive array:

```text
id                  string   execution identifier
name                string   execution name
kind                string   execution kind
status              string   lifecycle label
started_at          int      Unix epoch seconds
ended_at            int|null Unix epoch seconds
parent_execution_id string|null
linked_execution_ids string[]
sequence            int      monotonic sequence within the execution
context             array    normalized immutable context
result              array|null { status, throwable? }
```

The projection never includes secret or restricted context values beyond the
context's own redaction policy, and never serializes arbitrary PHP objects.

## Invariants

- An execution represents one logical runtime operation.
- Every artifact belongs to exactly one execution.
- An execution has at most one terminal outcome.
- Terminal executions cannot transition again.
- Nested executions use explicit parent or link identities.
- Ownership is represented by identity and relationships, not mandatory
  in-memory collections (ADR-003).
- Context is immutable; changes produce a new context (ADR-004).

## Public API usage

```php
declare(strict_types=1);

use ItPack\RunIt\Domain\Execution\Execution;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Context\ExecutionContext;

$execution = Execution::create(
    ExecutionDefinition::define('http.request', 'request')
        ->withContext(ExecutionContext::empty()),
);
```

Higher-level consumers usually start an execution through the application layer
(`ExecutionRunnerInterface` / `ExecutionFactoryInterface`) rather than building
the aggregate directly. See [`README.md`](../README.md) for the quick start.

## Related decisions

- ADR-001 — Execution is the root aggregate.
- ADR-002 — Domain artifacts are immutable.
- ADR-003 — Identity and relationships express ownership.
- ADR-004 — Explicit context propagation.
- ADR-017 — Opaque identifiers with pluggable generation.

See also: [`context.md`](context.md), [`activity.md`](activity.md).
