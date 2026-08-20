# Changelog

All notable changes to `it-pack/derive-it` are documented here. The format is
loosely based on [Keep a Changelog](https://keepachangelog.com/), and this project
adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- **Phase 9 — Edge-case & optimization hardening.**
- **Self / static / parent substitution (A1):** `TypeSubstitutor` rewrites
  `self`/`static`/`parent` return and parameter types to the derived interface
  FQCN of the enclosing class (and its parent), closing the leakage/semantic gap
  where a flat interface returned the concrete adaptee. Gated by
  `DeriveConfig::substituteSelfStatic` (default `true`).
- **Default-value constant rewrite (A2):** constant references inside parameter
  default-value expressions (`Enum::DEFAULT`) are rewritten through the
  substitution map when the constant's class is mapped.
- **Union / intersection adapter return wrapping (A3):** `AdapterGenerator`
  wraps each segment of a union return in its registered adapter and dispatches on
  the runtime type; intersection returns fall back to raw delegation.
- **Naming-collision detection (A5):** `Naming::assertNoCollision()` raises a
  typed `NamingCollisionException` when the derived interface FQCN would shadow an
  existing concrete type in the target namespace.
- **Empty-surface signal (A6):** `DeriveConfig::failOnEmpty` escalates a
  derivation with no methods and no constants to `EmptyDerivationException`.
- **Structured result (D1):** `DeriveIt::deriveWithResult()` returns a
  `DerivationResult` value object carrying the interface, FQCN, short name,
  source, method list, applied substitutions, and lifted constants.
- **Config parity (D3):** `with*()` withers now exist for every `DeriveConfig`
  flag (visibility, static, magic, constructors, substitution map, self/static,
  constants, only/skip methods, fail-on-empty, attribute pass-through, throws).
- **Typed exceptions (D4):** `NamingCollisionException`, `EmptyDerivationException`,
  and `LeakageDetectedException` replace the previous generic `LogicException`.
- **Surface richness (B1–B3):** `includeConstants` lifts public scalar/array
  constants into the interface; `attributePassThrough` keeps owned method/parameter
  attributes; `throwsCapture` carries `@throws` docblocks; `onlyMethods` /
  `skipMethods` allow surgical ports.
- **Family depth (C1–C4):** transitive substitution closure so chained maps resolve
  fully; owned-namespace prefix rewriting (`App\` → `App\Port\`); family
  auto-discovery from a root class graph (`deriveFamilyFromRoot`); and a single
  combined `deriveFamilySource()`.
- **Performance (E1/E2) & maintainability (F1/F3/F4):** `FamilyDeriver` memoizes
  each base interface per `FQCN + config hash`; a shared `SourceRenderer`
  consolidates interface→source assembly; `Naming` centralizes derived-interface
  naming; `RecursiveTraitResolver` memoizes per-node trait resolution.

- **Phase 10 (correctness-first cluster) — deep leakage & parity:**
  - **Deep leakage auditor (G1):** new `LeakageAuditor` scans every method
    return/parameter type after substitution and raises `LeakageDetectedException`
    for any non-builtin FQCN outside the target namespace / owned allow-list.
    Off by default via `DeriveConfig::auditLeakage`.
  - **Per-mode parity (G12):** `Naming::assertNoCollision()` now also checks
    `enum_exists` and runs in per-trait and family modes; a per-trait
    same-short-name trait clash now throws instead of silently overwriting.
  - **Family honors caller config (G2):** `FamilyDeriver::derive*` and
    `DeriveIt::deriveFamily*` accept and forward a `DeriveConfig` (previously
    forced to defaults).
  - **Injectable instance cache (G4):** `FamilyDeriver` replaced its process-global
    static cache with an instance-scoped cache plus `clearCache()` — removes a
    Phase-8-principle regression and un-masks Infection mutants.

### Changed
- `FamilyDeriver`'s memoization is now instance-scoped (constructor-injected cache
  + `clearCache()`) rather than a process-global static (Phase 10 / G4).
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
