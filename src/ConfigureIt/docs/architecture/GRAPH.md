GRAPH.md

This is the document that differentiates ConfigureIt from every other configuration library.

Most libraries stop here:

Array

ConfigureIt eventually evolves toward

Configuration Graph

Contents

1. Motivation

2. Node Model

3. Edge Model

4. Metadata

5. Provenance

6. Diagnostics

7. Explainability

8. Serialization

9. Future RDF Support

Node example

database.host

Value

localhost

Metadata

source

config/database.yaml

adapter

YamlLoader

priority

20

validated

true

schema

DatabaseSchema

origin

Environment Override

Graph edges

database

├── host

├── port

├── username

└── password

Later:

database.password

↓

SecretReference

↓

Vault

↓

AWS Secret

↓

Resolved Value

---

## Implemented (Phase 6)

Provenance is realised by `ItPack\ConfigureIt\ValueObject\Provenance` (interface
`ProvenanceInterface`), attached to every `ConfigurationNode` (see
`ConfigurationNodeInterface::provenance()`). It records: `resourceUri`, `format`,
`adapter` (loader class), `priority`, `sourceKind`, the `processorTrail`
(names of processors that rewrote the node), `overridden` + `overriddenSource`
(set by `ConfigurationGraph::put` when a higher-priority source wins), and
`validatedBy` (set by `ValidationProcessor`).

Load-time provenance is minted in `DocumentNormalizer`; the resolution pipeline
records each processor's `stageName()` (via `ProvenanceAwareProcessorInterface`)
in the trail of every node it touches.

Two services consume the graph:

- `ConfigurationDiagnostics` — produces a `DiagnosticsReport` (overrides, nodes by
  source/format, unresolved `${...}` placeholders) and emits `Observation`s through
  the `ResolutionReporter` (reusing the Execution spine).
- `ConfigurationDiffer` — produces a `ConfigurationDiff` between two graphs
  (added / removed / changed keys with both sides' provenance) for explainability
  and configuration diff tooling.

The frozen `ConfigurationSnapshot` exposes per-key provenance via
`ConfigurationSnapshotInterface::provenance(string $path)`.