# Backward compatibility

RunIt treats its public API as a compatibility surface. This document defines
that surface, the automated checker that guards it, and the review process for
changes.

## Public API scope

### CloudEvents SDK migration

RunIt now directly requires `cloudevents/sdk-php` `^1.2`. Strict SDK validation is
the default and may reject legacy inputs or alter structured JSON wire output;
the opt-in whole-event fallback is transitional and can be non-conformant. Mapper
and independently created serializer configuration must be reviewed separately.
Review public API and wire differences, downstream consumers and dependency
resolution before release; the API checker alone does not establish wire
compatibility. See [`integrations/cloudevents.md`](integrations/cloudevents.md).

### Runtime-local activity context (in progress)

The in-progress separate activity-context implementation preserves
the identity observed by current-execution consumers rather than attaching an
activity as the current execution. Integrations and custom runtime adapters will
need paired execution and activity storage appropriate to their concurrency model
and explicit propagation into new Fibers or coroutines; synchronous storage alone
does not isolate concurrent work. It adds an activity-storage port and concrete
builder methods; custom integrations must migrate to compatible paired storage.

The planned default for missing or mismatched execution context is execution-level
recording without an activity edge; opt-in strict behavior drops and reports
without payload by default or throws when explicitly configured. Recording with
the matching execution attached and no active activity remains valid. Consumers
must not interpret a recorder return value as proof of persistence or export.
Verified handles, not legacy activity IDs alone, establish activity relationships.
The existing open-activity limit and completion semantics remain unchanged.

Custom storage, recorders and activity-resolution extensions require compatibility
review. The API baseline has been updated with the maintainer's approval; consumer
migration and downstream verification remain outstanding. No normalization schema
change is included in this work.

### Current Phase 2 publication behavior

The SDK builder invokes a configured custom redactor on the original artifact
after an initial provenance check, then verifies and projects its output and
applies strict classifiers before the configured publisher stack. This also
applies when the redactor is explicitly null. Ordinary and health-aware custom
stack factories now receive `null` for the redactor and their stacks receive
classified projections, rather than raw artifacts. Factories that depended on
the redactor argument, original domain types, business IDs, or unclassified
fields need migration. SDK snapshot projections omit payload and content hash;
SDK measurements are dropped and reported, including with custom or null
redactors. Occurrence routing by the original type is unavailable on this path.

Standalone `PublisherStackFactory` composition without a shared issuing authority
now reports and drops unverifiable artifacts before sampling or delivery. A default
standalone `PublicationPipeline` does not automatically share the SDK authority.
Arbitrary custom composition can bypass this boundary and remains unsafe without
independent controls. OpenTelemetry uses redacted mapper methods for redacted
projections even when they also implement typed domain interfaces; custom mappers
depending on typed dispatch need review.
The planned configurable extension-input modes and independent classification
and provenance bypasses are not implemented; they remain deferred and are not
Phase 2 requirements. Phase 2 is complete: the overlapping activity provenance
blocker is resolved, the custom factory boundary delivers classified projections,
and all non-deferred exit criteria are demonstrated on the current checkout
(all `composer test` suites and all static quality gates pass). Downstream
packages must not be released until their issues are fixed and verified; downstream
migration and checks remain deferred. No release is made by this work. These
behavioral changes require compatibility review alongside any API and schema
differences; no baseline update is implied.

The public API is the set of symbols under these source directories:

- `src/Domain` — domain value objects, entities, and contracts.
- `src/Application` — application services and coordinators.
- `src/Contract` — all consumer- and adapter-facing contracts.
- `src/Adapter` — first-party adapters (PSR, OpenTelemetry, CloudEvents, …).
- `src/SDK` — consumer-facing SDK facades and builders.
- `src/Common` — shared public contracts (`FactoryInterface`,
  `StringableInterface`) implemented by public value objects.

The following namespaces are **internal** and are excluded from the
compatibility guarantee. They must never appear in a public signature
(parameter type, return type, extended/implemented class):

- `ItPack\RunIt\Behavior\` — reusable behavior traits mixed into value objects.
- `ItPack\RunIt\Conformance\` — adapter conformance harness and fixtures.

> `Conformance` is intentionally **internal** even though it is shipped and
> reachable via the `ItPack\RunIt\` PSR-4 mapping. Third-party conformance suites
> (see `docs/conformance.md`) use it, but it is **not** covered by the
> backward-compatibility guarantee; consumers must pin the exact RunIt version so
> the verifiers and fixtures they depend on stay aligned.

The checker fails closed: if a public symbol references an internal type, it is
reported as a break.

### Phase 0 (P0) public API additions

- `ItPack\RunIt\Contract\Shared\HttpExecutionContextInterface` is an additive
  public contract (under `src/Contract`, already in scope). It is consumed in
  place of the concrete `Adapter\Psr\Http\HttpExecutionContext` DTO, which remains
  `@api` and unchanged in its public `readonly` properties.
- `Psr7RequestContextExtractor::extract()` was widened to return the new interface
  (covariant return). This is backward compatible: callers expecting the concrete
  type still receive it, and callers typed against the interface gain the
  abstraction. `composer api:check` confirms no break; the baseline was
  regenerated.

### Refactoring plan (2026-08-23) public API additions

- `RunItBuilderInterface` gained three additive seams:
  `withSecurityLimits(SecurityLimits $limits)`,
  `withPublicationFailureHandler(?Closure)`, and
  `withSnapshotStoreFailureHandler(?Closure)`. Adding methods to a public
  interface is a source break for third-party implementers of the builder
  interface (acceptable pre-1.0 and deliberate); consumers who only *call* the
  builder are unaffected. The baseline was regenerated.
- `SecurityLimits`, `PublicationPipelineInterface`, `VerifierInterface`
  (`src/Conformance`), `SnapshotIntegrityVerifierInterface`, and
  `AttributeValidatorInterface` were formally marked `@api`. This is a
  documentation-level declaration of their existing public-contract status; no
  signatures changed.

### Refactoring plan Phase 1 (2026-08-23) lifecycle changes

Per ADR-023 the execution lifecycle matrix is now owned by the domain and
enforced structurally by the aggregate:

- `ExecutionStatusInterface` gained `canTransitionTo()`. **Breaking** for
  third-party status implementations, who must now express the closed matrix.
- `Execution::complete()` rejects re-completion of a terminal record (any
  target status) and illegal non-terminal transitions. Code that previously
  bypassed the coordinator to rebuild terminal records will now receive
  `ConflictingCompletionException` / `InvalidLifecycleTransitionException`.
  Coordinator semantics are unchanged (idempotent same-status repeats remain
  no-ops).
- `Activity::complete()` rejects completion of an already-terminal activity
  (`ConflictingActivityCompletionException`, new).
- `ExecutionFailure::__construct` became private; `of()` rejects non-terminal
  statuses. Direct construction was possible before and is no longer — use
  `ExecutionFailure::of()`.
- `ConflictingCompletionException` and `InvalidLifecycleTransitionException`
  additionally implement `LifecycleExceptionInterface`; existing catches on
  `InvalidValueExceptionInterface` keep working.
- `UnsupportedOperationExceptionInterface` was removed: it had zero
  implementations and zero consumers. Code referencing it must be updated.
- The measurement finite-value violation throws
  `InvalidMeasurementValueException` (implements
  `InvalidValueExceptionInterface`) instead of a bare
  `\InvalidArgumentException`; the security-limit range check throws
  `InvalidSecurityLimitException`. Both preserve message text.

### Refactoring plan Phase 0 (2026-08-24) additions

- `RunItBuilderInterface` gained one additive seam:
  `withPublisherStackFactory(PublisherStackFactoryInterface $factory)`. The
  builder now consumes the interface (previously it instantiated the concrete
  factory directly), so the factory port has a first-class consumer. Baseline
  regenerated; additive only.
- The default publisher stack gained recursion protection
  (`FailSafeArtifactPublisher` routes nested publishes to the health channel)
  and bound-exceeded drops are now reported through the configured publication
  failure handler as `BoundDropException` notices. Both are behavior additions;
  no signatures changed.
- Runner health-callback faults are contained with best-effort fixed, payload-free
  fallback logging, as in `CallbackHealthReporter`. Neither fallback guarantees
  delivery.

### Refactoring plan Phase 1 (2026-08-24) changes

- **Default redactor:** `RunItBuilder::build()` now installs a default
  redactor unless `withRedactor()` was explicitly called — including the
  explicit opt-out `withRedactor(null)` for the redactor itself. Matching top-level
  throwable messages and failed execution `result.throwable` are masked by the
  default redactor. Sampling now follows SDK projection and strict classification.
  Occurrence/snapshot
  payload masking is key-driven via a custom redactor. See `SECURITY.md` for
  current limitations before relying on this behavior.
- `BridgeConfig`/`BridgeConfigBuilder` gained
  `occurrenceLogAttributeAllowList` / `withOccurrenceLogAttributeAllowList()`:
  an optional allow-list restricting which occurrence extension attributes are
  exported to OTel logs. Empty (default) keeps the previous unrestricted copy.
- `FailSafeArtifactPublisher` reports publication failures to health *before*
  applying the failure policy, so fail-closed rethrows are observable.
- `BufferedArtifactPublisher::close()` reports remainder drops through the
  internal health reporter (previously counted silently).
- `BoundedArtifactPublisher` releases an execution's artifact counter on every
  terminal-snapshot exit path, including count/payload-dropped ones.

### Refactoring plan Phase 3 (2026-08-24) changes

Per ADR-024, activity completion now follows the same matrix discipline as
executions:

- `ActivityStatusInterface` gained `canTransitionTo()`. **Breaking** for
  third-party status implementations, mirroring the execution-status change.
- `Activity::complete()` rejects completing a running activity to a
  non-terminal status (`InvalidActivityTransitionException`, new, implements
  `LifecycleExceptionInterface`). Valid flows (`Succeeded`/`Failed` from
  `running`) are unaffected.
- `DefaultThrowableMapper` coerces throwable codes to `int` defensively;
  extension exceptions exposing non-int codes through shadowed properties no
  longer risk a `TypeError` in the failure-normalization path.
- `PayloadSectionByteEstimator` caps recursion at
  `SecurityLimits::maxSerializationDepth` (defense-in-depth; constructor gained
  an optional limits parameter with a safe default).
- `OccurrenceTypeFilter` docblock corrected: the filter *suppresses* all
  non-listed artifacts on its route (including non-occurrences); behavior is
  unchanged from previous releases.


## Stable normalization schema

Artifact serialization is a separate compatibility surface. The stable,
normalized field sets are documented in `config/normalization-schema.json`
(keyed per artifact type). Removing a field is a backward-compatibility break;
adding a field is tracked as a change to review. This file is checked
independently of the PHP API so schema drift is never hidden by code changes.

## The checker

`tools/api-compat/api-compat.php` is a dependency-free, reflection-based
checker (PHP only, no third-party package). It scans the public directories,
extracts the API surface, and diffs it against a committed baseline. It also
diffs `config/normalization-schema.json` against its baseline.

Configuration lives in `tools/api-compat/config.json` (public directories,
internal namespaces, baseline paths).

Composer scripts:

- `composer api:baseline` — write the current API surface and normalization
  schema to the baselines.
- `composer api:check` — diff the current surface against the baselines and
  exit `1` on any break. With no baseline present, it prints a notice and exits
  `0` (so the PR job is a no-op until a baseline exists).

> Roave Backward Compatibility Check was evaluated but caps at PHP 8.4 and pulls
> Symfony 7, which is incompatible with this package's PHP 8.5 / Symfony 8
> support matrix. The reflection-based checker is used instead so it runs on
> every supported PHP version.

## What is detected

- Removed public symbols (classes, interfaces, enums), methods, constants, and
  enum cases.
- Added methods to a **public interface** (breaks third-party implementers).
- Narrowed return types and narrowed parameter types (variance-aware:
  covariant returns, contravariant parameters).
- Added required parameters and parameters that became required.
- Internal types leaking into public signatures.
- Normalization schema field removals/additions (reported separately from the
  PHP API).

Added public methods on classes and added enum cases are reported as additive
notes, not breaks.

## Baseline policy

An initial baseline is committed so `composer api:check` is effective
immediately. At each **release candidate** the baseline is regenerated with
`composer api:baseline` and committed, freezing the API surface for that
release. Before `1.0.0` the baseline may be regenerated more freely; after
`1.0.0` it is treated as a stability contract.

The `1.0.0-rc.1` baseline generated in milestone M15.1 is the freeze for the
`1.0.0` stable release: it captures the 372 public symbols (147 interfaces, 206
classes, 13 enums) and the 8-artifact / 76-field normalization schema. Promotion
to `1.0.0` must not introduce any `break` against this baseline; additive
changes are allowed only with a documented, deliberate baseline regeneration.

## CI

`.github/workflows/quality.yml` runs the `api-compat` job (PHP 8.4) on every
push and pull request. It executes `composer api:check`; until a baseline
exists this job is a no-op, and once a baseline is committed it fails the build
on any detected break.

## Review process

For any change touching `src/Domain`, `src/Application`, `src/Contract`,
`src/Adapter`, `src/SDK`, `src/Common`, or `config/normalization-schema.json`:

1. Run `composer api:check` locally.
2. If it reports a break, either adjust the change to preserve compatibility or
   explicitly accept it and regenerate the baseline (`composer api:baseline`)
   with a documented reason.
3. Record the public API or compatibility impact in the pull request
   description (see `.github/pull_request_template.md`).
