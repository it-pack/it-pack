# DeriveIt examples

A set of small, runnable scripts that each showcase one building block or feature
of `derive-it`, using the fixtures shipped in `fixtures/`. Every example prints the
rendered interface (or adapter) source plus a one-line takeaway, so you can read
the output top-to-bottom and grasp the feature.

## Running

From the package root (the directory containing `vendor/`):

```bash
php examples/01-flat-interface.php
php examples/02-drop-constructor-static-magic.php
# ...etc
```

Or run them all in order:

```bash
for f in examples/[0-9]*.php; do php "$f"; done
```

Each script `require`s `examples/bootstrap.php`, which loads Composer's autoloader
(and with it the `_Fixture\*` demo classes) and defines a `show()` helper.

## The examples

| # | File | Showcases |
|---|------|-----------|
| 1 | `01-flat-interface.php` | **The core.** `DeriveIt::derive()` turns any class into a self-contained interface source. |
| 2 | `02-drop-constructor-static-magic.php` | **Dropping policies.** Constructors, static methods, and magic `__*` methods are dropped by default and can each be opted into. |
| 3 | `03-type-fidelity.php` | **Type grammar.** Union, intersection, variadic, by-ref, nullable, enum, and `self`/`static`/`parent` return types are reproduced faithfully. |
| 4 | `04-per-trait-interfaces.php` | **Interface Segregation.** One interface per used trait, composed via `extends` on the main interface. |
| 5 | `05-family-substitution.php` | **Family derivation.** `deriveFamily()` rewrites cross-referencing types (e.g. `Node` ⇄ `Tree`) to their derived interfaces. |
| 6 | `06-adapter-generation.php` | **Adapters.** `deriveAdapter()` emits the interface *and* a delegating adapter that wraps a concrete adaptee. |
| 7 | `07-configuration.php` | **DeriveConfig.** Every filtering decision (visibility, static, magic, constructors) is an explicit, defaulted flag. |
| 8 | `08-graph-walking.php` | **The foundation.** `ClassReflection` walks the full class/interface/trait graph the generators build on. |
| 9 | `09-leakage-invariant.php` | **No leakage.** Even when the source implements other interfaces, the derived interface extends nothing of the adaptee. |
| 10 | `10-dogfood-nette.php` | **Real-world use.** Derive an interface from `nette/php-generator`'s own `ClassType`, then load and verify it. |
| 11 | `11-adapter-wrapper-map.php` | **Adapters that wrap returned ports.** Port a returned type first, then a `returnWrapperMap` converts the concrete return value into its port (no vendor leakage). |
| 12 | `12-family-adapters.php` | **A family of adapters, auto-wired.** `deriveFamilyAdapter()` emits each member's interface + adapter and wraps cross references for you. |
| 13 | `13-adapter-with-config.php` | **Adapter with full config.** The `forAdapter()` preset keeps static methods; `methodRenames` reshapes the port's vocabulary. |
| 14 | `14-drift-detection.php` | **CI guardrail.** `DeriveIt::diff()` flags when a committed port diverges from the adaptee it was derived from. |
| 15 | `15-dogfood-adapter.php` | **Real-world adapter.** Emit interface + adapter for `nette/php-generator`'s `ClassType` and instantiate the generated adapter. |

## Fixtures used

The examples lean on the existing fixtures: `Service`, `ReadonlyClass`,
`StaticHeavyClass`, `MagicMethodsClass`, `UnionTypeClass`, `IntersectionTypeClass`,
`VariadicAndByRefClass`, `NullableTypeClass`, `ConsumesEnumParamClass`,
`SelfStaticParentReturnClass`, `VisibilityMixClass`, `ImplementsMultipleClass`,
`Stubs\ClassD`, `Node`/`Tree`, and `nette/php-generator\ClassType`.
