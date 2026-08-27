# Upgrading RunIt

This document explains how to upgrade `it-pack/run-it` and what changes are
considered compatibility-sensitive. It applies to both pre-release (`0.x`) and
stable (`1.0.0` and later) versions.

## Current release status

RunIt has published `1.0.0-rc.1`, which freezes the public API and the stable
normalization schema. The committed API baseline (`tools/api-compat/`) is the
stability contract: `composer api:check` must pass before promotion to `1.0.0`,
and any break detected there requires a deliberate baseline regeneration with a
documented reason. Treat the frozen surface as compatibility-sensitive; once
`1.0.0` is tagged, semantic versioning applies to all public symbols.

## What is compatibility-sensitive

After `1.0.0`, the following are treated as breaking and follow semantic
versioning:

- Public interfaces and their signatures (`src/Contract/**`).
- Public final classes, enums, and attributes.
- Normalized artifact fields produced by `normalize()` (see
  [`config/normalization-schema.json`](config/normalization-schema.json)).
- Occurrence type naming conventions.
- Adapter extension contracts and exposed exception types.
- Default security limits and publication failure policies.

The canonical, machine-checked list lives in
[`docs/compatibility.md`](docs/compatibility.md). Internal namespaces
(`ItPack\RunIt\Behavior\` and `ItPack\RunIt\Conformance\`) are excluded from the
backward-compatibility guarantee. Classes that live inside a public directory but
carry an `@internal` docblock (for example some `Application\Publication`
collaborators) are also excluded by convention.

## Automated compatibility checking

RunIt ships a reflection-based API compatibility checker that runs on the project's
PHP version and needs no external analysis dependencies:

```bash
composer api:check
```

It compares the current public API surface and normalization schema against the
tracked baseline in `tools/api-compat/` and fails on any breaking change. Before
`1.0.0`, maintainers refresh the baseline with `composer api:baseline` only when a
break is intentional. A `1.0.0` backward-compatibility baseline will be created in
milestone M15.

## Upgrade steps

1. Read [`docs/CHANGELOG.md`](docs/CHANGELOG.md) for the target version.
2. Run `composer update it-pack/run-it` and review the dependency diff.
3. Run `composer api:check` (or rely on CI) to detect public-API breaks.
4. Run `composer test`, `composer analyse`, and `composer architecture`.
5. If you implement a RunIt adapter or port, run the conformance suite
   (`composer test:conformance`); see [`docs/conformance.md`](docs/conformance.md).
6. Update any normalized-field consumers against
   [`config/normalization-schema.json`](config/normalization-schema.json).

## Deprecations

Stable releases use a deprecation period before removing public API. Pre-release
releases may remove API without notice; the changelog records such removals.

## Optional dependencies

OpenTelemetry, CloudEvents, PSR, Swoole, and OpenSwoole are optional adapters. An
upgrade may change an adapter's package or version constraint; core RunIt never
requires them. See [`docs/compatibility.md`](docs/compatibility.md) for the
integration compatibility matrix.
