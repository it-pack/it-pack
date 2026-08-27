# RunIt Vocabulary

RunIt has a machine-readable vocabulary that anchors every domain term used in
this documentation and in the public API. It is **documentation-only**: RDF and
Turtle are never a runtime dependency, and the code never loads or parses these
files. They exist so that a term means the same thing across adapters, docs, and
downstream packages, and so that occurrences, context keys, and kinds have stable
identifiers.

## Two artifacts

| File | Form | Role |
|---|---|---|
| [`vocab/run-it.ttl`](../vocab/run-it.ttl) | [OWL 2](https://www.w3.org/OWL/) ontology | The canonical *domain model*: classes, properties, controlled individuals (statuses, severities, classifications, measurement kinds, units), and the publication-failure policies. |
| [`vocab/run-it-taxonomy.ttl`](../vocab/run-it-taxonomy.ttl) | [SKOS](https://www.w3.org/2004/02/skos/core/) taxonomy | The *extensible* controlled vocabularies: execution kinds, activity kinds, standard context keys, and occurrence types. Consumers may add their own concepts. |

The ontology imports the taxonomy and links to it via `rdfs:seeAlso`; the
taxonomy imports the ontology and reuses its namespace. Together they define one
coherent vocabulary under a single base IRI.

## Namespaces

| Prefix | Base IRI | Defined in |
|---|---|---|
| `runit:` | `https://w3id.org/itpack/runit#` | ontology |
| `runit-tax:` | `https://w3id.org/itpack/runit/taxonomy#` | taxonomy |

A term's canonical IRI is the base IRI plus its local name, for example
`https://w3id.org/itpack/runit#Execution` or
`https://w3id.org/itpack/runit/taxonomy#ctx-correlation-id`.

## What the ontology defines

The ontology models execution itself — context, activities, measurements,
observations, occurrences, and snapshots — independently of any telemetry,
eventing, or logging standard. It contains:

- **Domain classes**: `Execution`, `ExecutionContext`, `ContextEntry`,
  `ContextClassification`, `Activity`, `Measurement`, `Observation`,
  `Occurrence`, `Snapshot`, and the abstract `Artifact` supertype shared by
  activities, measurements, observations, occurrences, and snapshots.
- **Status / severity / classification individuals**: `ExecutionStatus`
  (`Created`, `Running`, `Succeeded`, `Failed`, `Cancelled`, `TimedOut`),
  `ActivityStatus`, `ObservationSeverity` (mirroring PSR-3),
  `ContextClassification` (`Public` … `Secret`), and `SnapshotClassification`
  (`Checkpoint`, `Terminal`).
- **Measurement vocabulary**: `MeasurementKind` (`Counter`, `Gauge`, `Duration`,
  `Size`, `Ratio`, …) and `MeasurementUnit` individuals (`unitMillisecond`,
  `unitByte`, `unitCount`, …).
- **Publication policy**: `PublicationFailurePolicy` (`FailOpen`,
  `FailClosed`, `CollectAndContinue`, `ReportToFallback`).
- **Properties**: `hasActivity`, `hasMeasurement`, `hasObservation`,
  `hasOccurrence`, `hasSnapshot`, `hasContext`, `hasEntry`, `parentActivity`,
  `parentExecution`, and the data properties `executionId`, `activityKind`,
  `measurementValue`, `instant`, `duration`, and so on.

All public terms carry `vs:term_status "stable"` (the API is frozen at
`1.0.0-rc.1`) and `rdfs:isDefinedBy <https://w3id.org/itpack/runit>`.

## What the taxonomy defines

The taxonomy is a set of SKOS `ConceptScheme`s with recommended, extensible
concepts. Each concept has a `skos:notation` (the wire/string value) and a
`skos:definition`. The schemes are:

| Scheme | IRI | Example notations |
|---|---|---|
| Execution kinds | `runit-tax:ExecutionKinds` | `http.request`, `cli.command`, `queue.job`, `configuration.resolution`, `workflow`, `worker.iteration`, `scheduled.task`, `build`, `migration`, `event.processing`, `llm.pipeline`, `custom` |
| Activity kinds | `runit-tax:ActivityKinds` | `discover.files`, `read.resources`, `load.yaml`, `merge.documents`, `validate.schema`, `resolve.variables`, `download.secrets`, `build.graph`, `freeze.snapshot` |
| Context keys | `runit-tax:ContextKeys` | `environment`, `tenant.id`, `principal.id`, `correlation.id`, `trace.id`, `service.name`, `service.version` |
| Occurrence types | `runit-tax:OccurrenceTypes` | `itpack.configuration.loaded.v1`, `itpack.schema.validated.v1`, `itpack.snapshot.created.v1`, `itpack.pipeline.completed.v1`, `itpack.workflow.completed.v1` |

Kinds and keys are *extensible validated strings*, not closed enums: the domain
accepts any value that passes structural validation, and the taxonomy merely
recommends conventions. Occurrence types follow a stricter, versioned pattern
(`<org>.<domain>.<fact>.v<n>`) because they become CloudEvents `type` values.

## How the code references the vocabulary

Every public class or value object whose concept corresponds to a vocabulary
term carries an adjacent PHPDoc `@see` link to its canonical IRI. For example:

```php
/**
 * @see https://w3id.org/itpack/runit#Execution
 * @see https://w3id.org/itpack/runit/taxonomy#ExecutionKinds
 */
final readonly class ExecutionKind implements ExecutionKindInterface
{
    // ...
}
```

This keeps the documentation, the API, and the vocabulary in lock-step: when a
term is added or meaningfully changed, the vocabulary and the code reference move
together, and `docs/CHANGELOG.md` records the change. External standards are
referenced by their own specification (for example CloudEvents at
<https://cloudevents.io/> and OpenTelemetry at <https://opentelemetry.io/>) rather
than being modelled inside the RunIt ontology.

## Reuse by sibling packages

[AsyncIt](ecosystem.md) imports the RunIt ontology
(`owl:imports <https://w3id.org/itpack/runit>`) and reuses RunIt terms such as
`runit:Execution`, `runit:Activity`, `runit:duration`, `runit:startedAt`, and
`runit:endedAt` for its own task and runtime-adapter model. The RunIt vocabulary
is therefore a shared foundation across the ItPack ecosystem, not a RunIt-internal
artifact.

## Editing the vocabulary

- Add or change a **domain class, property, or controlled individual** in
  `vocab/run-it.ttl`, and add the matching `@see` link in the relevant public
  class.
- Add or change an **extensible kind, context key, or occurrence type** in
  `vocab/run-it-taxonomy.ttl`, keeping `skos:notation` stable for anything already
  emitted on the wire.
- Record every vocabulary change in `docs/CHANGELOG.md`.
- The vocabulary is not part of the PHPStan / DepTrac quality gates and is not
  published as a Composer artifact; treat the `.ttl` files as source of truth for
  terminology only.
