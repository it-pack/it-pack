# ADR-023: Port convergence — canonical port interfaces for the it-pack ecosystem

**Status:** Accepted
**Date:** 2026-10-28
**Source:** REPORT §7-R3, §7-R11, §8-R3, §8-R4, §8-R11; TODOS #5

## Context

The it-pack ecosystem has two independent origins of port/interface definitions
for the same cross-cutting concerns:

1. **RunIt** (`Contract/Port/*`) defines canonical interfaces for context storage,
   monotonic clock, serializer, snapshot store, artifact publisher, identifier
   generation, runtime context detection, and publication stack composition.
2. **CommandIt** and **DisenchantIt** maintain their own mirror definitions of
   clock, context, serializer, and storage ports — by design (ADR-0001 for
   CommandIt, ADR-0005 for DisenchantIt). These packages stay RunIt-agnostic and
   do not import RunIt types; their mirrors are intentionally self-contained.
3. **Bridges** (`AsyncIt`, `ConfigureItRunit`, `DeriveItRunit`, `CommitItRunit`,
   `HashItAsync`, `RunItThirdPartySample`) consume RunIt's execution model.
   `AsyncIt` already implements RunIt's `ContextStorageInterface`,
   `ContextTokenInterface`, `MonotonicClockInterface`,
   `ArtifactPublisherInterface`, and `IdentifierGeneratorInterface` directly.
   The other bridges consume RunIt's domain types and SDK but were at risk of
   independently defining their own port interfaces.

The rule-of-three convergence trigger has fired: four bridges reimplement the same
RunIt port families (`AsyncIt`, `ConfigureItRunit`, `DeriveItRunit`,
`CommitItRunit`), joining the CommandIt/DisenchantIt mirrors. Without a
convergence strategy, future bridges risk copying the interfaces verbatim.

## Decision

**Conservatively: RunIt's `Contract/Port/*` interfaces are the canonical port
definitions for the it-pack ecosystem.** Sibling packages that integrate with RunIt
must implement and consume these interfaces directly; they must not define their
own parallel interfaces for the same concerns.

### Rationale

- RunIt is at RC1 (`1.0.0-rc.1`) with a frozen API and BC enforcement via
  `tools/api-compat/api-baseline.json`. Its port interfaces are stable.
- `AsyncIt` already follows this pattern successfully — it implements
  `ContextStorageInterface`, `ContextTokenInterface`, `MonotonicClockInterface`,
  `ArtifactPublisherInterface`, and `IdentifierGeneratorInterface` from RunIt's
  `Contract/Port/` namespace.
- Creating a new `it-pack/port-it` package would introduce a new dependency
  cluster and delay bridge development. RunIt's ports are already production-tested
  and stable.
- The CommandIt/DisenchantIt mirror packages are an intentional exception
  (documented in their respective ADRs) — they deliberately do not import RunIt.

### Constraints

- Bridge packages that import RunIt (`AsyncIt`, `ConfigureItRunit`,
  `DeriveItRunit`, `CommitItRunit`, `HashItAsync`, `RunItThirdPartySample`)
  **must** use `ItPack\RunIt\Contract\Port\*` as their port interface sources.
  They must not define duplicate interfaces for clock, context storage,
  serialization, snapshot storage, artifact publishing, or identifier generation.
- Mirror packages (`CommandIt`, `DisenchantIt`) **may** retain their own port
  definitions — this is an intentional architectural constraint (vendor-neutral
  cores), not technical debt. These mirrors are tracked in their respective ADRs
  and are not subject to convergence.
- New bridge packages must reference RunIt's port interfaces from day one. The
  `forbidSibling()` helper in `tools/qa/phparkitect/forbid-sibling.php` enforces
  that mirror packages (CommandIt, DisenchantIt) cannot import RunIt.
- Occurrence-name registries are centralized under `RunIt/vocab/` to prevent
  naming drift across consumers.

## Consequences

- **Positive:** Single source of truth for run-time adapter ports; no interface
  duplication in the bridge layer; new bridges have a clear contract to implement.
- **Negative:** Mirrors in CommandIt/DisenchantIt remain (by design) — they cannot
  be eliminated without breaking the vendor-neutral core constraint.
- **Migration:** Verify all four bridge packages already implement or consume
  RunIt's `Contract/Port/*` interfaces. Document any that define duplicate
  interfaces and refactor them to use RunIt's canonical ports.

## Related ADRs

- ADR-0001: RunIt integration via ports (CommandIt)
- ADR-0005: RunIt exclusion (DisenchantIt)
- ADR-016: Bridge package split criteria

## Occurrence-name registries

Per §8-R4, occurrence names are centralized under `RunIt/vocab/`:
- `src/RunIt/vocab/run-it.ttl` — canonical OWL vocabulary
- `src/RunIt/vocab/run-it-taxonomy.ttl` — SKOS taxonomy for occurrence types,
  context keys, and kind values

Bridge packages must reference the corresponding vocabulary resource via `@see`
in code and link to it in documentation, rather than defining their own
occurrence name registries.
