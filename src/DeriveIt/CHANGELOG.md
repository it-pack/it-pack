# Changelog

All notable changes to `it-pack/derive-it` are documented here. The format is
loosely based on [Keep a Changelog](https://keepachangelog.com/), and this project
adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- `DeriveIt::deriveAdapter()` — generates a delegating adapter implementing the
  derived interface and wrapping a concrete adaptee (Phase 5).
- `returnWrapperMap` option for adapter generation: wrap returned derived types in
  a registered adapter class so the port stays free of concrete types.

## [0.4.0] - 2026-08-20

### Added
- Adapter generation (Phase 5): `Generator/AdapterGenerator` and golden
  `*.adapter.php` snapshot tests.

## [0.3.0] - 2026-08-20

### Added
- Type-substitution map (`DeriveConfig::typeSubstitutionMap`) and
  `TypeSubstitutor` (Phase 4).
- Family derivation: `DeriveIt::deriveFamily()` rewrites cross-referencing types
  across a set of classes to their derived interface FQCNs.

## [0.2.0] - 2026-08-20

### Added
- Interface-per-trait mode (Phase 3): `DeriveIt::derivePerTrait()` and
  `DeriveIt::deriveTraitInterfaces()`; one interface per used trait with conflict
  resolution and leakage guard.

## [0.1.0] - 2026-08-20

### Added
- Flat interface derivation (Phase 1): `DeriveIt::derive()` /
  `DeriveIt::deriveInterface()` produce a self-contained `Nette\PhpGenerator`
  interface from any class.
- Filtering controls (Phase 2): `DeriveConfig` plus `VisibilityFilter`,
  `StaticFilter`, `MagicMethodFilter`, `ConstructorFilter`.

### Fixed
- Phase 0 tooling: `php >= 8.4` platform declared; Codeception modules moved to
  `require-dev`; `nikic/php-parser` dropped; PHPStan and PHP-CS-Fixer green.
