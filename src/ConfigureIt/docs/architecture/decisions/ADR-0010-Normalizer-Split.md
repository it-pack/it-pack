ADR-0010

Document Normalizer Separated from Pipeline

Status

Accepted

Context

`ConfigurationPipeline::run()` (src/Core/ConfigurationPipeline.php:37-71) performs two
distinct responsibilities: (a) normalising each `ConfigurationDocumentInterface` into
`list<ConfigurationNodeInterface>` via the private `flatten()` helper
(:77-98, which also re-implements associative-array detection in `isAssociative()`
:103-110), and (b) applying the registered processors. This mixes a *normalisation*
concern with a *pipeline* concern (SRP), and makes the flatten logic harder to unit
test in isolation.

Decision

Extract a `DocumentNormalizer` (Core port) responsible for
`ConfigurationDocumentInterface → list<ConfigurationNodeInterface>`, built on the
`Key` value object (ADR-0009). `ConfigurationPipeline::run()` then becomes:
normalise each document into nodes → `graph->put(...)` → run processors. The
`isAssociative()` helper moves to a small `@pure` array utility or the `Key` object.

The `ConfigurationGraphInterface` must NOT absorb normalisation; it stays focused on
priority-merge + snapshot production (preserving the ISP-positive finding in
docs/REFACTORING_PLAN.md §11).

Consequences

- Pipeline is thinner and single-purpose (SRP); normalisation is independently testable.
- Clearer dependency direction: Pipeline depends on Normalizer + Graph + Processors,
  none of which import one another transitively.
- One extra collaborator to wire in the composition root (ADR-0002 / AGENTS.md).

Alternatives

- Leave `flatten()` private in the pipeline (rejected: SRP, weak testability).
- Push normalisation into the graph (rejected: violates ISP, bloats graph interface).

References

- src/Core/ConfigurationPipeline.php
- src/Contract/ConfigurationGraphInterface.php
- docs/REFACTORING_PLAN.md §5, §11
- ADR-0009 (Key), ADR-0002 (Ports & Adapters)
