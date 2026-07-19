ADR-0007

Lazy Documents, Caching & Weak References

Status

Proposed

Context

`ConfigurationGraph::toArray()` rebuilds the entire nested tree on every call and is
invoked twice per resolution (schema validation + snapshot freeze) in
`ConfigurationResolver::resolve()` (src/Core/ConfigurationResolver.php:69-85).
`PhpLoader` materialises a temp file and `require`s it on every `load()`
(src/Adapter/PhpLoader.php:28). The `ResolutionReporter` retains every occurrence in
an unbounded `list` (src/Execution/ResolutionReporter.php:22).

MODEL.md lists "Live Resolution" (hot-reload, incremental graph updates) and
"Observability" as future concepts, both of which repeat resolution and would pay
these costs repeatedly.

Decision

Adopt PHP 8.4 language features to make resolution lazy and cacheable:

- **Lazy Objects / Ghost Objects** for `ConfigurationNode` values that are expensive
  or indirect (e.g. secret/Vault references from MODEL.md). Values are realised on
  first access only.
- **Memoisation** of `ConfigurationGraph::toArray()`, invalidated on `put()`.
- **WeakMap caching** of parsed `ConfigurationDocumentInterface` keyed by the
  immutable `ConfigurationResourceInterface`, owned by the LoaderFactory (ADR-0006),
  so repeated resolution of the same resource reuses the document and lets GC
  reclaim it when the resource is dropped.
- **WeakMap** keyed by `ConfigurationSourceInterface` inside the reporter, to attach
  provenance/occurrences to a source without forcing it to stay alive.

Consequences

- Lower CPU/memory for large configs and for Live Resolution / repeated resolution.
- Slightly more moving parts; lazy nodes must stay transparently immutable once
  realised (ADR-0003 immutability preserved).
- Caching must be invalidated correctly on graph mutation (memoisation cleared on
  `put()`).

Alternatives

- Eager re-parse every time (rejected: wasteful for Live Resolution).
- A global static cache (rejected: AGENTS.md forbids global state; WeakMap is
  scoped to the factory/reporter instance).

References

- src/Core/ConfigurationGraph.php (toArray)
- src/Adapter/PhpLoader.php
- src/Execution/ResolutionReporter.php
- docs/REFACTORING_PLAN.md §7, §8
- ADR-0003 Immutable Snapshots
- docs/EXECUTION.md (Observations / Occurrences)
