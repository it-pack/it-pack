# Changelog

All notable changes to RunIt are documented in this file.

RunIt is pre-release software. Until `1.0.0`, public APIs may change between
pre-release versions.

## 1.0.0-rc.1

First release candidate for the stable `1.0.0` API (milestone M15.1).

- Freezes the public API surface at **372 public symbols** (147 interfaces,
  206 classes, 13 enums) under `src/Domain`, `src/Application`, `src/Contract`,
  `src/Adapter`, `src/SDK`, and `src/Common`.
- Freezes the stable normalization schema (`config/normalization-schema.json`) at
  8 artifact types and 76 fields.
- Regenerates the backward-compatibility baseline in `tools/api-compat/`; this
  baseline is the stability contract for `1.0.0` and is enforced by
  `composer api:check` in CI.
- Explicitly marks internal namespaces: `ItPack\RunIt\Behavior\` and
  `ItPack\RunIt\Conformance\` are excluded from the backward-compatibility
  guarantee (the conformance harness is shipped and reachable, but consumers must
  pin the exact RunIt version).
- Confirms no abandoned or experimental APIs remain in the public tree.
- The detailed change history accumulated during pre-`1.0.0` development is
  recorded in the `Unreleased` sections below; this entry marks the point at
  which that surface was frozen for the release candidate.

## Unreleased

- Composite context storage rejects foreign, forged, and previously detached
  wrapper tokens by identity while preserving retry after an out-of-order detach.
  Callers must detach using the exact token issued by the same composite instance.
- **Breaking:** `ContextTokenInterface` requires `id(): int`. Implementations
  must provide a stable storage-defined integer for the token's lifetime.
  The value need not be unique; storage implementations must continue to
  validate token ownership and detachment order independently. Consumers with
  custom tokens must implement the method before upgrading. This intentionally
  updates the `1.0.0-rc.1` API baseline, including the existing `id()` methods
  on the four first-party token classes.
- Established the M0 repository, quality-tooling, documentation, and CI
  foundation.
- Added the M1.1 base contracts under `src/Contract/Domain/`:
  - `NormalizableInterface` for deterministic, bounded normalization.
  - `StringIdentifierInterface` for immutable string-backed identifiers.
  - `ExecutionArtifactInterface` as the root marker for all semantic artifacts.
  - `ExecutionOwnedInterface` and `ActivityOwnedInterface` for ownership.
  - `TimestampedInterface`, `SequencedInterface`, and `AttributedInterface`.
  - Public exception-category marker interfaces under `src/Contract/Exception/`:
    `RunItExceptionInterface`, `InvalidValueExceptionInterface`,
    `LifecycleExceptionInterface`, and `UnsupportedOperationExceptionInterface`.
  - Contract-level unit tests validate determinism, value equality, ownership,
    primitive projections, and marker hierarchies.
  - Vocabulary: added the abstract `runit:Artifact` class to `vocab/run-it.ttl`
    as the common supertype of `Activity`, `Measurement`, `Observation`,
    `Occurrence`, and `Snapshot`, and corrected docblock `@see` links that pointed
    at undefined IRIs (`#artifact` → `#Artifact`, `#measurement` → `#Measurement`)
    or at external standards inside the RunIt namespace (`#CloudEvent` →
    <https://cloudevents.io/>, `#OpenTelemetry` → <https://opentelemetry.io/>).
    The kind/context-key value objects now also link to their SKOS schemes
    (`runit-tax:ActivityKinds`, `runit-tax:ExecutionKinds`,
    `runit-tax:ContextKeys`, `runit-tax:OccurrenceTypes`).

## Unreleased — Phase 0 (P0)

- Made `composer analyse` (PHPStan level max) pass by clearing the 5 genuine
  errors; the 15 `shipmonk.deadMethod` notices remain accepted noise and are
  triaged in Phase 1 (T1.2).
- Added `ItPack\RunIt\Contract\Shared\HttpExecutionContextInterface`, a public
  contract for `Adapter\Psr\Http\HttpExecutionContext` (closes the one real DIP
  leak flagged by `runit.requireInterface`). The concrete DTO keeps its public
  `readonly` properties for backward compatibility and implements the interface
  through matching accessors (`getName()`, `getKind()`, `getCorrelationId()`,
  `getContext()`, `getAttributes()`). `HttpContextExtractorInterface::extract()`
  and `Psr7RequestContextExtractor::extract()` now return the contract.
- Tightened `DefaultArtifactRedactor::maskKeys()` and `maskThrowable()` iterable
  types to `array<array-key, mixed>` so the security/redaction component satisfies
  level-max without `missingType.iterableValue`. Internal-only change.
- Regenerated `tools/api-compat/api-baseline.json` and
  `normalization-schema-baseline.json`; the additive public symbols and the
  covariant return widening on `Psr7RequestContextExtractor::extract()` introduce
  no backward-compatibility break.

## Unreleased — Phase 1 (P1)

- Documented `Contract\Shared\HttpExecutionContextInterface` as a first-class
  public contract: it is marked `@api` with a `@see https://w3id.org/itpack/runit#Execution`
  vocabulary link, and `docs/compatibility.md` records the addition and the
  backward-safe covariant `extract()` return.
- Triaged the 15 `shipmonk.deadMethod` notices. Every one is a deliberate public
  surface — 6 public interface/port contract methods and 9 symmetric fluent
  `with*` withers on the `readonly` `SecurityLimits` value object — so none were
  deleted (that would be a backward-compatibility break) and none are suppressed
  with `@phpstan-ignore`. They are enumerated as a keep-list in `docs/qa.md`;
  `composer analyse` therefore still reports exactly 15 errors, matching the
  documented kept set.

## Unreleased — Phase 2 (P2)

 Phase 2 was analysed; the design was retained where unification would be unsafe, and the
 composition-root factory extraction was executed. Rationale per task:

 - **T2.1 (VO validation unification):** `ValidatedStringValueBehavior` validates with
   `StringValueValidator` and exposes `equals(StringValueInterface)`, so it only fits VOs
   implementing `StringValueInterface` (the existing 7 name/kind/type VOs). The candidates
   are not safely unifiable: `ContextKey`/`AttributeKey` use the **stricter**
   `ContextValueValidator`/`AttributeValidator` (adopting the trait would weaken validation —
   a security regression); `ContextSource` implements a different interface so the trait would
   change its `equals` contract; `ContextEntry`/`Occurrence`/`Snapshot` are complex non-string
   VOs; `ExecutionSnapshot` already injects its validator. No code change was made.
 - **T2.3 (factory extraction) — executed:** added `Contract/Port/PublisherStackFactoryInterface`
   and `Application/Factory/PublisherStackFactory` that compose redaction (closest to the base)
   then sampling around the configured base publisher, mirroring the previous inlined
   `RunItBuilder::build()` wiring. The builder now delegates to the factory; its `with*` seams
   and copy-on-write behavior are unchanged. `composer architecture`, `composer analyse`,
   `composer api:check`, and `composer test` (704 tests) are green.
 - **T2.2 (lazy objects):** deferred by analysis, not by the test gate (which runs green). It
   is a speculative optimization with no benchmark evidence of a construction-cost problem; the
   genuinely-expensive OpenTelemetry bridge is injected into the builder/runner, never constructed
   there, so lazy-init in the builder would defer no heavy work. Revisit only if a benchmark
   shows eager construction of rarely-used heavy infrastructure.
 - **T2.4 (property hooks):** deferred by analysis. The candidate projection getters
   (`isExportable`/`isPropagatable`/`isSerializable`, `isError`, `isTerminal`, `has`, `get`,
   `isEmpty`, `getIterator`) are **all declared in public interfaces**, so converting them to
   property hooks would break the contract (a hook is read as a property, not called as a method).
   Direct values already use `public readonly` properties, so there is no getter boilerplate to
   replace; no safe, non-BC-breaking conversion target exists.

  All static gates remain green: `composer cs:check`, `composer architecture`,
  `composer api:check`, and `composer analyse` (the 15 accepted `shipmonk.deadMethod` notices).

## Unreleased — Phase 3 (P3)

Phase 3 (polish) completed. All gates green (`composer cs:check`, `composer architecture`,
`composer api:check`, `composer analyse` at 15 accepted `shipmonk.deadMethod`, `composer test`
704 tests).

- **T3.1 (centralize redaction default):** added `DefaultContextRedactionPolicy::default()`
  (additive `@api` named constructor) and replaced both `new DefaultContextRedactionPolicy()`
  sites (in `ExecutionDefinition` and `Execution`) with it, so the default policy choice lives
  in one place. `composer api:baseline` regenerated.
- **T3.2 (complexity guard):** added `Tools\PhpStan\RunitRules\Rule\ComplexityGuardRule`, a
  `src/`-scoped rule counting branch-bearing nodes per method as a cognitive-complexity proxy,
  registered in `tools/phpstan/runit-rules/extension.neon` with threshold 19 (the current worst
  offender). It is a regression guard: nothing is flagged today, but newly-added over-complex
  methods are caught. Documented in `docs/qa.md`.
- **T3.3 (named constructors):** verified already satisfied. All five wide-constructor VOs
  already expose ergonomic named constructors (`CloudEvent::create()`, `Measurement::{counter,
  gauge, duration, size, ratio}()`, `Observation::{debug,info,notice,warning,error,critical,
  alert,emergency}()`, `Timeout::of()`, `SecurityLimits::default()` + `with*` withers), so no
  new builders were needed.
- **T3.4 (recorder `$clock`):** confirmed not a false positive in practice — PHPStan does not
  flag any recorder `$clock` (resolved via `InstantClockBehavior::now()`), and every recorder
  reads it when timestamping. No suppression or removal; documented in `docs/qa.md`.

## Unreleased — M1.2 identifiers

- Implemented the shared domain identifier primitives (milestone M1.2):
  - Six identifier contracts and immutable value objects: `ExecutionId`,
    `ActivityId`, `ObservationId`, `OccurrenceId`, `SnapshotId`, and
    `CorrelationId`, each `final`, `readonly`, and interface-backed.
  - `StringIdentifierValidator` enforcing structural rules: non-empty, bounded
    to 128 bytes, visible-ASCII only (no whitespace, control, or non-ASCII
    bytes), anchored so a trailing newline is rejected.
  - `InvalidIdentifierException` implementing `InvalidValueExceptionInterface`
    without echoing the rejected value.
  - `IdentifierGeneratorInterface` port and `StringIdentifierFactory` for
    pluggable, reflection-free generation bound to a specific identifier type.
  - Added vocabulary terms `runit:observationId` and `runit:correlationId`, and
    ADR-017 recording the opaque-string-with-pluggable-generation decision.
  - Added unit, validator, factory, and deterministic property-based tests.
  - Renamed the misspelled `ExecutionIdInterfacee` to `ExecutionIdInterface` and
    removed the speculative reflection-based `ImmutableStringIdentifierFactory`.

## Unreleased — M1.3 names and kinds

- Implemented the shared domain name and kind primitives (milestone M1.3):
  - Seven `final`, `readonly` value objects: `ExecutionName`, `ExecutionKind`,
    `ActivityName`, `ActivityKind`, `MeasurementName`, `OccurrenceType`, and
    `SnapshotType`, each interface-backed.
  - Shared `StringProviderInterface` / `StringValueInterface` contracts, the
    `StringValueValidator` (non-empty, ≤128 bytes, letters/digits/`.`/`-`/`_`
    only), `InvalidStringValueException`, and the `ValidatedStringValueBehavior`
    / `StringProviderBehavior` traits.
  - `OccurrenceType` enforces a stricter lowercase, dot-separated, versioned
    format (e.g. `itpack.configuration.loaded.v1`) and exposes `version()`.
  - ADR-018 records the decision to use extensible validated strings rather
    than closed enums; occurrence types stay low-cardinality and independent of
    PHP class names.
  - Added unit tests for every value object, occurrence-type format and version
    extraction, and validator boundaries.
## Unreleased — M1.4 time and sequencing

- Implemented the time and sequencing primitives (milestone M1.4):
  - Immutable `Instant` (Unix epoch seconds, no timezone) and `Duration`
    (integer nanoseconds, never negative) value objects in `Domain/Shared`.
  - Immutable `ArtifactSequence` and the `SequenceAllocatorInterface` port, with
    `InMemorySequenceAllocator` (per-execution monotonic counter, first
    allocation returns zero).
  - `MonotonicClockInterface` port keeping wall-clock seconds and monotonic
    nanoseconds separate, with `SystemMonotonicClock` (`time()` + `hrtime()`)
    and a `DeterministicMonotonicClock` test double.
  - ADR-019 records the monotonic-time / integer-nanosecond / immutable-sequence
    decision; `runit:instant` was added to the vocabulary.
  - Added unit tests for instant/duration conversion and rejection of negatives,
    increasing per-execution sequence allocation, and wall/monotonic separation.
