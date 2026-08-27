# RunIt Coding and Architecture Standards

The authoritative, complete standards live in [`AGENTS.md`](../AGENTS.md) (section
"PHP Standards" and "Coding style"). This page lists only the **RunIt-specific**
supplements that are easy to miss when reading the general rules.

## RunIt-specific supplements

- **Execution vocabulary first.** RunIt uses the vocabulary defined in
  [`docs/EXECUTION.md`](../EXECUTION.md). Prefer domain terms (execution, activity,
  measurement, observation, occurrence, snapshot, context) over adapter synonyms
  (trace, span, log, event, metadata bag).
- **Immutable semantic model.** Concrete domain artifacts are `final`, `readonly`
  unless controlled lifecycle mutation is required, and never retain a runtime scope,
  clock, or recorder. See [`docs/concepts/semantic-model.md`](../concepts/semantic-model.md).
- **Ports over concrete adapters.** External effects cross the boundary through
  `src/Contract/Port`. Application and domain must never depend on a concrete adapter.
  See [`hexagonal-architecture.md`](hexagonal-architecture.md).
- **Vocabulary alignment (RDF is documentation only).** `vocab/run-it.ttl` is the
  canonical OWL vocabulary; `vocab/run-it-taxonomy.ttl` supplies extensible
  recommended kinds, context keys, and occurrence types. A public implementation that
  corresponds to a vocabulary term must include an adjacent PHPDoc `@see` link to its
  canonical IRI, and the matching public documentation must link to the vocabulary
  resource. New public terms require a vocabulary update in the same change.
- **No abandoned or experimental APIs.** The `1.0.0-rc.1` surface contains no
  `@experimental` / `@deprecated` markers; the API baseline in
  `tools/api-compat/api-baseline.json` is frozen and protected by `composer api:check`.

For dependency rules, the machine-generated layer graph is in
[`dependency-graph.md`](dependency-graph.md) and enforced by `composer architecture`.
