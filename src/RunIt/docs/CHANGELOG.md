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
