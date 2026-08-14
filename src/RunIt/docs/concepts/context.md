# Execution context

RunIt carries per-execution metadata as an immutable, classified, bounded
**execution context**. A context is a collection of typed entries, each binding a
validated key to a bounded value, a classification, and optional metadata. The
model is immutable: adding or removing an entry returns a new context and never
mutates the receiver. The design is described in
[`runit:ExecutionContext`](https://w3id.org/itpack/runit#ExecutionContext).

## Types

| Contract | Value object / service | Notes |
|---|---|---|
| `ExecutionContextInterface` | `ItPack\RunIt\Domain\Context\ExecutionContext` | Immutable collection |
| `ContextEntryInterface` | `ItPack\RunIt\Domain\Context\ContextEntry` | Classified entry |
| `ContextKeyInterface` | `ItPack\RunIt\Domain\Context\ContextKey` | Validated key |
| `ContextClassificationInterface` | `ItPack\RunIt\Domain\Context\ContextClassification` | Closed classification enum |
| `ContextCollisionPolicy` | `ItPack\RunIt\Domain\Context\ContextCollisionPolicy` | Merge strategy |
| `ContextRedactionPolicyInterface` | `ItPack\RunIt\Domain\Context\DefaultContextRedactionPolicy` | Default deny-secret redaction |
| `ContextPropagationPolicyInterface` | `ItPack\RunIt\Domain\Context\DefaultContextPropagationPolicy` | Delegates to classification |
| `ContextSerializationPolicyInterface` | `ItPack\RunIt\Domain\Context\DefaultContextSerializationPolicy` | Delegates to classification |

## Classification

Every entry carries one of five closed classifications. A classification decides
the default edge behavior; it is not an access-control decision.

| Classification | Export | Propagate | Serialize | Redaction on export |
|---|---|---|---|---|
| `Public` | yes | yes | yes | kept |
| `Internal` | no | yes | yes | dropped |
| `Confidential` | no | no | yes | dropped |
| `Restricted` | no | no | yes | masked (value replaced by a marker) |
| `Secret` | no | no | no | dropped |

Classification does not replace authorization: it is a default applied when
context leaves the process, is propagated to child work, or is written to a
snapshot.

## Entries and values

A `ContextEntry` is built through a named constructor that sets the
classification (`public()`, `internal()`, `confidential()`, `restricted()`,
`secret()`). Values are scalars, `null`, or scalar-only lists/maps; objects and
resources are rejected by the type system (top level) and by the value
validator (nested). Values are bounded by `ContextValueValidator`
(`MAX_VALUE_LENGTH` = 4096 bytes, `MAX_ARRAY_ELEMENTS` = 64). Entries may carry
an optional expiry `Instant` and an optional `ContextSource` identifier.

## Collection and merging

`ExecutionContext` is bounded (`MAX_ENTRIES` = 64) and offers copy-on-write
`with()`/`without()`, `has()`/`find()`/`get()` queries, iteration, and
deterministic, key-sorted `toArray()` normalization. `merge()` combines two
contexts with an explicit `ContextCollisionPolicy`:

- `Replace` — the incoming context wins on a shared key.
- `Keep` — the receiving context wins on a shared key.
- `Throw` — a shared key raises `InvalidContextValueException`.

Parent-child inheritance is expressed by merging a child over a parent with
`Replace` (child wins), so a child context transparently inherits parent
entries unless it overrides them.

## Redaction

`DefaultContextRedactionPolicy` implements the deny-secret default: public
entries are kept, internal and confidential entries are dropped (not exportable),
restricted entries are masked (the key is kept but the value is replaced with a
redaction marker), and secret entries are dropped entirely. The receiver context
is never mutated.

## Standard vocabulary

The following keys are recommended. They are advisory except where noted as
stable; consumers may add their own namespaced keys.

| Key | Meaning | Stability |
|---|---|---|
| `environment` | Deployment/runtime environment name | stable |
| `tenant.id` | Tenant or account identifier | stable |
| `workspace.id` | Workspace or project identifier | advisory |
| `locale` | Request or process locale | advisory |
| `principal.id` | Authenticated principal identifier | stable |
| `principal.type` | Principal kind (user, service, …) | advisory |
| `correlation.id` | Correlation identifier for the work | stable |
| `trace.id` | Tracing identifier | stable |
| `service.name` | Originating service name | stable |
| `service.version` | Originating service version | advisory |
| `deployment.environment` | Deployment environment label | stable |
| `configuration.snapshot_id` | Linked configuration snapshot identifier | advisory |

## Security posture

The context is a potential data-exfiltration path, so it is deliberately narrow:
keys and values are validated and bounded, objects and resources are rejected,
and classification plus redaction bound what can leave the process. Secret
redaction is deny-by-default (ADR-012): secret values are never serialized and
never exported.
