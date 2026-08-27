# RunIt Architecture Decision Records

RunIt's design is recorded as a set of Architecture Decision Records (ADRs). Each ADR
captures one deliberate, load-bearing choice. Read the **Principles** summary first,
then dive into any record by theme.

## Principles every reader should know

These five decisions explain *why RunIt looks the way it does*:

1. **Execution is the root aggregate** ([ADR-001](ADR-001-execution-root-aggregate.md)) —
   every context, activity, and artifact belongs to exactly one execution.
2. **Domain artifacts are immutable** ([ADR-002](ADR-002-immutable-domain-artifacts.md)) —
   no mutable sessions, no arbitrary serialization; `normalize()` is deterministic.
3. **External standards are adapters** ([ADR-006](ADR-006-external-standards-are-adapters.md)) —
   OpenTelemetry, CloudEvents, PSR, and Swoole describe RunIt; they do not define it.
4. **Business exceptions have priority** ([ADR-008](ADR-008-business-exceptions-have-priority.md)) —
   a publication failure never replaces the original exception.
5. **Sensitive values are deny-by-default** ([ADR-012](ADR-012-sensitive-values-deny-by-default.md)) —
   secrets are never exported or serialized; restricted values are masked on redaction.

## By theme

### Domain model
- [ADR-001](ADR-001-execution-root-aggregate.md) — Execution is the root aggregate.
- [ADR-002](ADR-002-immutable-domain-artifacts.md) — Domain artifacts are immutable value objects.
- [ADR-003](ADR-003-identity-relationships.md) — Ownership via identity and relationships, not in-memory collections.
- [ADR-008](ADR-008-business-exceptions-have-priority.md) — Original business exceptions take priority over adapter failures.
- [ADR-011](ADR-011-versioned-occurrence-names.md) — Occurrence types are stable, lowercase, versioned (`name.vN`).
- [ADR-017](ADR-017-opaque-identifiers-pluggable-generation.md) — Identifiers are opaque with pluggable generation.
- [ADR-018](ADR-018-extensible-string-names-and-kinds.md) — Names and kinds are extensible validated strings, not closed enums.
- [ADR-019](ADR-019-monotonic-time-and-immutable-sequences.md) — Monotonic time and immutable sequences order artifacts.
- [ADR-023](ADR-023-aggregate-owned-lifecycle-matrix.md) — Aggregate-owned lifecycle matrix (execution/activity terminals).
- [ADR-024](ADR-024-activity-completion-matrix.md) — Activity completion matrix and open-activity policy.

### Architecture & boundaries
- [ADR-005](ADR-005-ambient-context-adapter-backed.md) — Ambient context is adapter-backed convenience, not the canonical API.
- [ADR-006](ADR-006-external-standards-are-adapters.md) — External standards are adapters, not domain definitions.
- [ADR-010](ADR-010-core-has-no-mandatory-integration.md) — Core has no mandatory external integration.
- [ADR-015](ADR-015-final-interface-backed-classes.md) — Concrete classes are `final` and interface-backed.
- [ADR-016](ADR-016-bridge-package-split-criteria.md) — Criteria for splitting optional bridges into separate packages.

### Context & propagation
- [ADR-004](ADR-004-explicit-context-propagation.md) — Context propagation is explicit; changes produce a new context.
- [ADR-012](ADR-012-sensitive-values-deny-by-default.md) — Sensitive values are deny-by-default.
- [ADR-013](ADR-013-fiber-swoole-adapters.md) — Fiber/Swoole context is stored per coroutine, never in global mutable state.

### Time & sequencing
- [ADR-009](ADR-009-monotonic-duration-time.md) — Durations use a monotonic clock.
- [ADR-019](ADR-019-monotonic-time-and-immutable-sequences.md) — Monotonic time and immutable sequences.

### Publication, security & resilience
- [ADR-007](ADR-007-publication-fails-open.md) — Publication fails open with fallback reporting by default.
- [ADR-012](ADR-012-sensitive-values-deny-by-default.md) — Sensitive values are deny-by-default (see above).
- [ADR-014](ADR-014-publication-recursion-guards.md) — Publication recursion guards prevent self-observation.

### Integrations & packaging
- [ADR-020](ADR-020-opentelemetry-bridge-placement.md) — OpenTelemetry bridge placement and mapping boundaries.
- [ADR-021](ADR-021-cloudevents-bridge-placement.md) — CloudEvents bridge placement and bidirectional mapping.
- [ADR-022](ADR-022-swoole-adapter-packaging.md) — Swoole adapter packaging and worker lifecycle.
- [ADR-016](ADR-016-bridge-package-split-criteria.md) — Bridge package split criteria (see above).

## Using ADRs

- ADRs are immutable records of *decided* design. To propose a change, write a new ADR
  that supersedes the relevant one; do not silently edit a decided record.
- The enforcement behind these decisions lives in `deptrac.yaml` (see
  [architecture/dependency-graph.md](../architecture/dependency-graph.md)) and the
  backward-compatibility baseline in `tools/api-compat/`.
