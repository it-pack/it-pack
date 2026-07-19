ADR-0009

Shared `Key` / `Path` Value Object

Status

Proposed

Context

Dotted configuration keys (e.g. `database.host`, `app.debug`) are a first-class
concept in ConfigureIt's ubiquitous language, yet they have no type: plain `string`
paths are threaded through `ConfigurationSnapshot::get()`/`has()`
(src/Core/ConfigurationSnapshot.php:34-64), `ConfigurationGraph::assign()`
(src/Core/ConfigurationGraph.php:94), and `ConfigurationPipeline::flatten()`
(src/Core/ConfigurationPipeline.php:77) — three independent re-implementations of
dotted-key tree walking (docs/REFACTORING_PLAN.md §2, §3). Phase 2 intends array-index
segments (`cache.pools[0].dsn`), which will multiply this duplication.

Decision

Add a small immutable Core value object `Key` (e.g. `src/Core/Key.php`) that owns all
key/segment logic:

- `Key::fromString(string): self` (parses/validates segments; future: array-index segments)
- `Key::child(string): self`
- `Key::get(array $tree): mixed` / `Key::has(array $tree): bool`
- `Key::set(array &$tree, mixed $value): void`
- `Key::toString(): string`

`ConfigurationSnapshot`, `ConfigurationGraph`, and `ConfigurationPipeline` all use
`Key` instead of hand-rolling traversal. `Key` is a Core concept with no adapter
dependency.

Consequences

- Single source of truth for key semantics (DRY); array-index segments land in one place.
- Snapshot `get()`/`has()` no longer duplicate descent; pipeline flattening reuses it.
- Slightly more type ceremony at call sites (construct a `Key`), but clearer intent.

Alternatives

- Keep three bespoke traversals (rejected: DRY; will drift on array-index support).
- Put traversal on the array helpers (rejected: key semantics are domain, not generic).

References

- src/Core/ConfigurationSnapshot.php
- src/Core/ConfigurationGraph.php
- src/Core/ConfigurationPipeline.php
- docs/REFACTORING_PLAN.md §2, §3, §6
