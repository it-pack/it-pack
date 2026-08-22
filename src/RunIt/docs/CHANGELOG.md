# Changelog

All notable changes to RunIt are documented in this file.

RunIt is pre-release software. Until `1.0.0`, public APIs may change between
pre-release versions.

## Unreleased

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
