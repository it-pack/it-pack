# RunIt Glossary

Every RunIt term, with a link to its canonical concept page and (where one exists) its
vocabulary IRI in [`vocab/run-it.ttl`](https://w3id.org/itpack/runit). RunIt vocabulary
is documentation-only; RDF is never a runtime dependency.

## Core model

| Term | Meaning | See | Vocabulary |
|---|---|---|---|
| **Execution** | One logical runtime operation; the root aggregate everything belongs to. | [concepts/execution.md](concepts/execution.md) | `runit:Execution` |
| **Activity** | A bounded unit of work inside an execution (a "step"). | [concepts/activity.md](concepts/activity.md) | `runit:Activity` |
| **Measurement** | A quantitative, named, typed, unit-bearing value. | [concepts/measurement.md](concepts/measurement.md) | `runit:Measurement` |
| **Observation** | A qualitative note (info/warning/error); severities mirror PSR-3. | [concepts/observation.md](concepts/observation.md) | `runit:Observation` |
| **Occurrence** | An immutable, versioned fact (e.g. `order.placed.v1`); maps to CloudEvents. | [concepts/occurrence.md](concepts/occurrence.md) | `runit:Occurrence` |
| **Snapshot** | An immutable projection of resulting state, bound to a content hash. | [concepts/snapshot.md](concepts/snapshot.md) | `runit:Snapshot` |
| **Context** | Immutable, classified per-execution metadata. | [concepts/context.md](concepts/context.md) | `runit:ExecutionContext` |
| **Artifact** | Any of the above domain records; each is owned by exactly one execution. | [concepts/semantic-model.md](concepts/semantic-model.md) | — |

## Identifiers, names, and time

| Term | Meaning | See |
|---|---|---|
| **Identifier** | Opaque, generated value object (execution id, activity id, …). | [concepts/identifiers.md](concepts/identifiers.md) |
| **Name** | An extensible, validated string for an execution/activity/measurement. | [concepts/names-and-kinds.md](concepts/names-and-kinds.md) |
| **Kind** | An extensible, validated string classifying the operation type. | [concepts/names-and-kinds.md](concepts/names-and-kinds.md) |
| **Sequence** | A monotonic, non-negative ordering number within an execution. | [concepts/time-and-sequencing.md](concepts/time-and-sequencing.md) |
| **Timestamp** | A Unix epoch-second attached to every artifact. | [concepts/time-and-sequencing.md](concepts/time-and-sequencing.md) |
| **Normalization** | Deterministic, bounded `normalize()` projection of an artifact. | [concepts/semantic-model.md](concepts/semantic-model.md) |

## Architecture & runtime

| Term | Meaning | See |
|---|---|---|
| **Port** | An interface for an external effect (clock, identity, serialization, publication, storage). | [architecture/hexagonal-architecture.md](architecture/hexagonal-architecture.md) |
| **Adapter** | A concrete implementation of a port; vendors (OTel, CloudEvents, PSR, Swoole) stay at the edge. | [architecture/hexagonal-architecture.md](architecture/hexagonal-architecture.md) |
| **Publisher** | The single port (`ArtifactPublisherInterface`) through which artifacts leave the runtime. | [architecture/publication.md](architecture/publication.md) |
| **Recursion guard** | A mechanism preventing publication from observing itself. | [architecture/publication.md](architecture/publication.md), [adr/ADR-014](adr/ADR-014-publication-recursion-guards.md) |
| **Ambient context** | Convenience storage that restores context around a call; not the canonical API. | [concepts/context.md](concepts/context.md), [architecture/context-propagation.md](architecture/context-propagation.md) |
| **Redaction** | Classification-driven masking of restricted/secret values before export. | [architecture/security.md](architecture/security.md), [concepts/context.md](concepts/context.md) |
| **Attribute** | A metadata marker (`#[Execution]`, `#[Activity]`) for non-intrusive adoption. | [concepts/attributes.md](concepts/attributes.md) |

## Related reading

- [Execution Model](EXECUTION.md) — the narrative behind these terms.
- [Semantic model](concepts/semantic-model.md) — the shared base contracts every artifact implements.
- [ADR index](adr/README.md) — the decisions that shaped these definitions.
