# DeriveIt — In-depth Feature Review, Edge-case Analysis & Optimization Plan

> Companion to `REVIEW.md`, `ROADMAP.md`, and `IMPLEMENTATION_PLAN.md`.
> Prepared: 2026-08-20 · Scope: `it-pack/derive-it` (`src/`), tests, and `examples/`.
> Method: source reading of the full generation pipeline, inspection of committed
> golden files (`tests/_expected/*.interface.php`), and reasoning about PHP
> reflection / Nette semantics.

---

## 1. Current capability snapshot

DeriveIt derives **self-contained interfaces (and adapters)** from PHP classes via
four public modes on `DeriveIt`:

| Mode | Method | Output |
|------|--------|--------|
| Flat interface | `derive()` / `deriveInterface()` | one `InterfaceType`, no `extends` |
| Per-trait | `derivePerTrait()` / `deriveTraitInterfaces()` | one interface per trait + composed main |
| Family | `deriveFamily()` | N interfaces; cross-references rewritten via a build-time map |
| Adapter | `deriveAdapter()` | derived interface + delegating `final` adapter in one file |

Every mode is driven by `DeriveConfig` (immutable flags: `visibility`,
`includeStatic`, `includeMagic`, `dropConstruct`, `dropDestruct`,
`typeSubstitutionMap`) and the filter chain `Visibility → Static → Magic →
Constructor`. Types are rebuilt with `Nette\PhpGenerator\Factory::
fromMethodReflection()` (the single source of truth for union / intersection /
nullable / variadic / by-ref typing). The **leakage invariant** — a derived
interface must not `extends`/reference the adaptee's own hierarchy — is asserted
by `InterfaceDeriver::assertSelfContained()` and
`PerTraitInterfaceDeriver::assertPerTraitSelfContained()`.

All five quality gates are green (113 unit tests, deptrac, PHPStan L5, CS,
Infection MSI 86%). The pipeline is correct for the common case and well-tested.

---

## 2. What is solid (do not regress)

- **Flat derivation is leakage-clean by construction** — `getMethods()` on the
  concrete class gives the effective surface, and `assertSelfContained()` fails the
  run if anything slips through.
- **Type grammar fidelity** is delegated to Nette, not hand-rolled — union,
  intersection, nullable, variadic, by-ref, readonly, enum params all render
  correctly (verified by golden files for `UnionTypeClass`, `IntersectionTypeClass`,
  `ConsumesEnumParamClass`, `VariadicAndByRefClass`, `ReadonlyClass`).
- **Per-trait** correctly de-duplicates shared method names across traits and
  composes a main interface that `extends` only the derived trait interfaces.
- **Family substitution** rewrites sibling references to derived interface FQCNs.
- **Adapter generation** delegates correctly, wraps substituted returns when a
  wrapper is registered, and handles variadic / by-ref / default values.
- **Hardening done (Phase 8):** resolvers are instance-injected (no static state),
  per-trait `extends` renders clean short names, dead code removed.

---

## 3. Edge cases & quirks NOT yet covered

These are concrete gaps found by reading the pipeline and the golden outputs. Each
lists the observed behaviour, why it matters, and a recommended direction.

### 3.1 `self` / `static` / `parent` are never substituted (leakage + adapter break)
- **Evidence:** `tests/_expected/SelfStaticParentReturnClass.interface.php` renders
  `withSelf(): SelfStaticParentReturnClass;` (the **concrete** class) and
  `withStatic(): static;`. `TypeSubstitutor::walkNamed()` leaves `self`/`static`/
  `parent` untouched unless they happen to be in the map.
- **Why it matters:** a flat-derived interface that returns its own concrete class
  **violates the leakage invariant** (it names the adaptee) and is semantically
  wrong — the port should reference its own interface, not the concrete type. For
  adapters it is worse: an adapter method declared `static`/`self` returns the
  *adaptee's* instance, not the adapter, so the declared return type lies.
- **Recommended:** when deriving a class (or a family that includes it), rewrite
  `self`/`static`/`parent` to the **derived interface FQCN** of the enclosing class
  (and its parent, for `parent`). This is the single highest-value correctness fix.

### 3.2 Default-value expressions referencing class constants leak
- **Evidence:** `ConsumesEnumParamClass.interface.php` renders
  `withDistribution(\SimpleBackedEnum $distribution = SimpleBackedEnum::DEFAULT): static;`.
  The default `SimpleBackedEnum::DEFAULT` is a class-constant fetch that is emitted
  verbatim — it references (and, across namespaces, depends on) the concrete enum.
- **Why it matters:** default values are not run through `TypeSubstitutor`, so they
  can re-introduce a concrete dependency the interface was supposed to shed.
- **Recommended:** rewrite the *type* of defaulted params (already done) **and** the
  *constant references inside default values* via the substitution map (or drop
  defaults whose value references a non-owned class when strict leakage is required).

### 3.3 Flat mode silently drops the adaptee's own interfaces (contract loss)
- A class implementing `Countable` (or any owned/third-party interface) derives an
  interface with `extends []`. The derived port therefore does **not** satisfy
  `Countable`, so an adapter of a `Countable` is not itself `Countable`.
- **Tension:** the leakage invariant forbids `extends`; but a *port* that loses the
  original contract is also undesirable. This is the central design trade-off.
- **Recommended (non-trivial):** an opt-in `reExposeOwnedInterfaces` policy that
  re-`extends` only interfaces the user explicitly owns (configurable allow-list),
  keeping third-party ones out. Default stays strict (no extends).

### 3.4 Union / intersection return types are not wrapped by the adapter
- `AdapterGenerator::wrapperFor()` bails when a return type has more than one
  segment (`$segments !== 1`). So `Foo|Bar` where both have adapters is returned
  **raw**, re-introducing concrete types across the adapter boundary.
- **Recommended:** compute the wrapper per segment (and for `Foo&Bar` intersective
  returns) so each concrete segment is wrapped; if any segment lacks a wrapper,
  fall back to raw for that segment.

### 3.5 `static`/`self` returns in the adapter body
- Even after 3.1 fixes the *interface*, the adapter's delegating body returns
  `$this->adaptee->method()`, which yields the adaptee's `static`/`self`, not the
  adapter's. The wrapper logic does not special-case this.
- **Recommended:** when the substituted return type is `static`/`self`, wrap the
  call in the adapter class itself (or document the limitation and exclude such
  methods from adapters by default).

### 3.6 Constants are never derived
- Only methods are extracted. `ConstantsVisibilityClass` / `TypedConstantsClass`
  fixtures exist but their constants are ignored. Interfaces *can* declare
  constants (interface constants are legal PHP).
- **Recommended (optional feature):** an `includeConstants` flag that lifts public
  constants into the derived interface — valuable for value-object-style ports.

### 3.7 Method/parameter attributes and `throws` are lost
- `MethodSignatureFactory` copies only signature shape; no attribute or `throws`
  metadata is carried. `#[Override]`, framework attributes, and PHP 8.4 `throws`
  annotations disappear from the port.
- **Recommended:** opt-in attribute pass-through (only for attributes the user
  owns — attribute class FQCNs are themselves a leakage surface) and capture of
  `throws` where the runtime exposes it.

### 3.8 Empty public surface produces a silent empty interface
- `EmptyClass.interface.php` is `interface EmptyClassInterface {}`. Valid, but there
  is no signal that nothing was derived.
- **Recommended:** emit a structured result (see §5-D) so callers can detect
  "zero methods derived" and decide; optionally a `failOnEmpty` config flag.

### 3.9 No naming-collision detection
- `derivedInterfaceShortName()` always appends `Interface`. If `FooInterface`
  already exists in the target namespace, the rendered file silently redefines or
  shadows it.
- **Recommended:** collision check against the namespace + a configurable naming
  policy / suffix; raise a typed `NamingCollisionException`.

### 3.10 Unusual derivation targets (enums, traits, abstract classes)
- Deriving from an **enum** lifts its methods (cases are constants, not methods) —
  works but the result is an interface of an enum with no clear adapter story.
  Deriving from a **trait** directly works via `getMethods()` but the leakage
  invariant and naming are untested. Deriving from an **abstract class** with
  abstract methods is fine, but covariant/abstract redeclaration is untested.
- **Recommended:** explicitly document supported targets; add golden/snapshot tests
  for enum/trait/abstract inputs and a guard that the target is instantiable (or a
  deliberate `deriveFromNonInstantiable` opt-in).

### 3.11 `includeMagic` + keep-constructor yields an interface with `__construct`
- With `includeMagic=true` and `dropConstruct=false`, `__construct` survives into the
  interface. Interfaces *may* declare constructors, but an adapter would then need
  to forward construction — currently `AdapterGenerator` always emits its own
  `__construct(adaptee)`.
- **Recommended:** treat constructor-in-interface as a documented limitation; either
  exclude `__construct` from adapter mode or special-case it.

### 3.12 Substitution is one-level and family-only
- `TypeSubstitutor` rewrites only the types present in the (explicit or family)
  map. It does not follow the types *referenced by* substituted types, nor apply a
  namespace-scoped "own all types in `App\*`" policy.
- **Recommended:** transitive substitution (rewrite types referenced by already-
  substituted types) and an owned-namespace substitution policy for large internal
  codebases.

---

## 4. Brainstormed additional features

Grouped by intent. Items marked **(core)** are correctness/leakage fixes from §3;
the rest are enhancements.

### A. Correctness & leakage hardening (core)
- **A1** Substitute `self`/`static`/`parent` → derived interface FQCN (§3.1, §3.5).
- **A2** Rewrite default-value constant references via the substitution map (§3.2).
- **A3** Union/intersection adapter return wrapping (§3.4).
- **A4** Opt-in `reExposeOwnedInterfaces` allow-list (§3.3).
- **A5** Naming-collision detection + configurable policy (§3.9).
- **A6** `failOnEmpty` / empty-surface signal (§3.8).

### B. Richer surface
- **B1** `includeConstants` — lift public constants into the interface (§3.6).
- **B2** Opt-in attribute pass-through (owned attributes only) + `throws` capture (§3.7).
- **B3** `onlyMethods` / `skipMethods` allow/deny lists for surgical ports.
- **B4** `rename`/alias policy (rename a derived method to avoid clashes with the
      adaptee's own interface).

### C. Family & substitution depth
- **C1** Transitive type substitution (§3.12).
- **C2** Owned-namespace substitution policy (`App\*` → `App\Port\*`).
- **C3** Auto-discover a family from a root class graph (walk `getClassesRecursively`
      + referenced types) instead of an explicit class list.
- **C4** `deriveFamily()` returning a combined, single-file or multi-file source
      (currently it returns a map of per-class strings).

### D. Developer experience
- **D1** `DerivationResult` value object (cf. DisenchantIt's `DisenchantResult`):
      `{interface, fqcn, shortName, methods[], substitutionsApplied[], collisions[]}`
      instead of a bare string — enables tooling, tests, and the §3.8 signal.
- **D2** `DeriveIt::deriveMany(iterable)` and `analyze(class)` (structured report,
      no rendering) for callers that only want metadata.
- **D3** Config parity — add `with*()` withers for **all** flags (only
      `withIncludeStatic` exists today; inconsistent).
- **D4** Typed, specific exceptions (`NamingCollisionException`,
      `EmptyDerivationException`, `LeakageDetectedException`) instead of generic
      `LogicException`.
- **D5** Dry-run / diff mode: given existing source, show what *would* change.
- **D6** Validate the input is a class/trait the user intends (instantiable guard,
      §3.10) with a clear message.

### E. Performance
- **E1** Memoize derived interfaces keyed by `class FQCN + config hash` (a single
      process deriving the same class repeatedly — e.g. inside `deriveFamily` or an
      IDE loop — currently re-reflects every time).
- **E2** In `deriveFamily()`, derive each member's *base* (un-substituted) interface
      once, then apply the substitution map only at render (avoids N full re-derives).
- **E3** Share a single `Nette\PhpGenerator\Factory` instance across derivations
      (currently one per `MethodSignatureFactory`).
- **E4** Lazily build the resolver graph and cache `get*Recursively()` results per
      `ReflectionClass` instance.
- **E5** Optional PSR-6/array cache adapter for the reflection results so large
      codebases don't re-parse on every call.

### F. Maintainability
- **F1** Extract a shared `SourceRenderer` (today `InterfaceDeriver::deriveSource`,
      `DeriveIt::renderInterface()`, and `AdapterGenerator::generateSource()` each
      re-implement the `PhpNamespace` + `PhpFile` assembly — the exact spot where
      the H3 FQCN-simplification fix lived, so consolidating reduces regression risk).
- **F2** Make `FamilyDeriver` and `AdapterGenerator` implement `DeriverInterface`
      (today only `InterfaceDeriver` and `PerTraitInterfaceDeriver` do) so the four
      modes share one extension seam.
- **F3** Centralize the `derivedInterfaceShortName` / `derivedInterfaceFqcn` helpers
      (currently static on `InterfaceDeriver`) into a small `Naming` service used by
      all derivers and the adapter generator.
- **F4** Reduce `RecursiveTraitResolver` redundant re-walking (noted in REVIEW §6.1-4)
      by memoizing per-node resolution.

---

## 5. Risks & non-goals

- **`self`/`static` substitution (A1)** changes interface shapes and may surprise
  callers who relied on the concrete return type; gate it behind a config flag and
  keep the current behaviour as the default until a major version.
- **Re-exposing interfaces (A4)** trades strict leakage for completeness — must stay
  opt-in and allow-list scoped, never automatic, to honour the core invariant.
- **Attribute pass-through (B2)** is itself a leakage surface (attribute FQCNs); only
  pass through attributes the user owns.
- **Performance caching (E1/E2)** adds memory cost and must key on config + class
  identity to avoid stale results across different configs.
- **Non-goals:** turning DeriveIt into a full refactoring/rewriter tool (that is
  DisenchantIt's domain); supporting PHP generics/templates (no native PHP generics);
  emitting implementations beyond the simple delegating adapter.

---

## 6. Recommended sequencing (prioritized)

1. **Correctness first (A1–A3, A5, A6).** These close real leakage/semantic gaps
   with small, well-contained changes and high test value. A1 is the headline fix.
2. **Structured result + DX (D1, D3, D4).** `DerivationResult` unblocks A5/A6 signals,
   better exceptions, and cleaner tests; cheap and high-leverage.
3. **Surface richness (B1–B3)** as demand arises; B1 (constants) is the most useful.
4. **Family depth (C1–C3)** for larger internal codebases; C3 auto-discovery is the
   biggest win but also the biggest build.
5. **Performance (E1–E4)** once usage scales (IDE loops, whole-repo scans).
6. **Maintainability (F1–F4)** continuously, opportunistically alongside the above.

Each item is small enough to land as its own PR with golden/snapshot tests and must
keep all five gates green (never let `test:unit`, `cs:check`, `analyse`,
`architecture`, or `infection` regress — per `AGENTS.md`).
