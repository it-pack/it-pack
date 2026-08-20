ADR-0006

Loader Factory & Registry

Status

Accepted

Context

Phase 1 resolves configuration by iterating `iterable<ConfigurationLoaderInterface>`
inside `ConfigurationResolver::build()` (src/Core/ConfigurationResolver.php:100)
and by hand-rolling `new ConfigurationDocument(...)`. Every new format (YAML, TOML,
NEON — ROADMAP Phase 2) currently means (a) adding another loader with a repeated
`supports(Format)` method and (b) the resolver silently gaining another loop
branch. This is an Open/Closed Principle violation: extending supported formats
requires modifying the resolver. It is also DRY duplication of the
format→loader dispatch.

Decision

Introduce a `ConfigurationLoaderFactory` port (owned by Core) that owns the `Format → Loader`
mapping. The factory is constructed from `iterable<ConfigurationLoaderInterface>`
and exposes `build(Format): ConfigurationLoaderInterface` (throwing
`ResolutionException` when no loader supports the format). The resolver depends on
the factory (Dependency Inversion), not on the raw loader list.

Optionally, loaders self-register via an attribute (e.g. `#[Supports(Format::YAML)]`)
so the registry is assembled by reflection from a single source of truth — mirroring
the attribute-driven algorithm catalogue used by the sibling `HashIt` package.

Consequences

- Adding a format requires only a new adapter + one registration; the resolver is
  untouched (OCP).
- The `supports()` method on each loader becomes an internal registry detail rather
  than a public dispatch the resolver scans.
- One obvious place to cache parsed documents per resource (see ADR-0007).

Alternatives

- Keep the loop in the resolver (rejected: OCP/DRY violation, grows with formats).
- Service-locator / global registry (rejected: hidden dependencies, AGENTS.md
  forbids global state).

References

- src/Core/ConfigurationResolver.php (build)
- docs/REFACTORING_PLAN.md §1, §4, §10
- ADR-0002 Ports & Adapters
- HashIt attribute-driven catalogue (src/HashIt/README.md)
