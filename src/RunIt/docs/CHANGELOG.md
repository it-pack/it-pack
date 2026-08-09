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

