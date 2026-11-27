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

Replace references to `CloudEvent::SPEC_VERSION_KEY` with
`CloudEventAttributeKey::SpecVersion->value` from the
`ItPack\RunIt\Adapter\CloudEvents` namespace. The constant has been removed;
CloudEvents structured JSON field names have not changed as a result.

If you explicitly select `FailExecutionOnOpenActivitiesPolicy` with the default
runner, successful operations with open activities now publish a failed terminal
status. Adjust status-based alerts and consumers accordingly, or select the default
`AutoFailOpenActivitiesPolicy` to retain operation-derived status. The fixed
`open_activity_policy` failure-description class is a policy marker, not a thrown
business exception. Returned values and original business exceptions are unchanged.

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

OpenTelemetry, CloudEvents, PSR, Swoole, and OpenSwoole adapters are opt-in, but
the RunIt package directly requires `cloudevents/sdk-php` and
`open-telemetry/sem-conv`. Other integration packages may be optional. See
[`integrations/compatibility-matrix.md`](integrations/compatibility-matrix.md).

## Behavior changes

- CloudEvents structured JSON now defaults to SDK validation. Previously accepted
  legacy events can fail and serialized output can differ. Test real producer and
  consumer fixtures, configure a transitional whole-event legacy fallback only when
  necessary, and configure independently constructed serializers separately. See
  [`integrations/cloudevents.md`](integrations/cloudevents.md).
- An omitted or `null` `onPublicationFailure` publication-failure handler now
  reports to the replaceable SDK default health sink (the PHP error log) instead
  of being silent, including when a `DefaultExecutionRunner` is constructed
  directly. This is the default destination selected for Phase 4. No public
  signature changed (`onPublicationFailure` remains `?Closure`).
- Explicit silence is opted into by passing a no-op closure as the publication
  failure handler, e.g. `withPublicationFailureHandler(static function (): void
  {})` or `onPublicationFailure: static function (Throwable $e): void {}`. The
  runner invokes it directly and reports nothing on that path; independent
  health paths may still report.
- `FailExecutionOnOpenActivitiesPolicy` with the default runner now marks an
  otherwise successful execution `Failed` when activities remain open. The fixed
  `open_activity_policy` failure-description class is a policy marker, not a
  thrown business exception. Returned values and original business exceptions are
  unchanged. Select the default `AutoFailOpenActivitiesPolicy` to retain
  operation-derived status.
- Fallback diagnostics are now fixed and payload-free: the runner's direct
  reporting path and the publisher's re-entry fallback no longer carry the
  original publication failure or reporting-fault context. Operators should not
  correlate a fallback notice to a specific cause from its contents.
- Neither open-activity policy itself closes the activity. See
  `architecture/lifecycle.md` for current limitations.
