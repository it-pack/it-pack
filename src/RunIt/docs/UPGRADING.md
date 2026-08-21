# Upgrading RunIt

This document explains how to upgrade `it-pack/run-it` and what changes are
considered compatibility-sensitive. It applies to both pre-release (`0.x`) and
stable (`1.0.0` and later) versions.

## Current release status

RunIt has not yet published a stable `1.0.0`. Until then, the `main` branch is the
supported line and **breaking changes may occur without a major version bump**.
Treat each upgrade as potentially breaking and review the diff plus the
[`docs/CHANGELOG.md`](docs/CHANGELOG.md).

## What is compatibility-sensitive

After `1.0.0`, the following are treated as breaking and follow semantic
versioning:

- Public interfaces and their signatures (`src/Contract/**`).
- Public final classes, enums, and attributes.
- Normalized artifact fields produced by `normalize()` (see
  [`docs/normalization-schema.json`](docs/normalization-schema.json)).
- Occurrence type naming conventions.
- Adapter extension contracts and exposed exception types.
- Default security limits and publication failure policies.

The canonical, machine-checked list lives in
[`docs/compatibility.md`](docs/compatibility.md). Internal namespaces
(`src/Application/**` implementation detail, `src/Behavior/**` traits, and adapter
internals) are not covered by the backward-compatibility guarantee.

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
   [`docs/normalization-schema.json`](docs/normalization-schema.json).

## Deprecations

Stable releases use a deprecation period before removing public API. Pre-release
releases may remove API without notice; the changelog records such removals.

## Optional dependencies

OpenTelemetry, CloudEvents, PSR, Swoole, and OpenSwoole are optional adapters. An
upgrade may change an adapter's package or version constraint; core RunIt never
requires them. See [`docs/compatibility.md`](docs/compatibility.md) for the
integration compatibility matrix.
