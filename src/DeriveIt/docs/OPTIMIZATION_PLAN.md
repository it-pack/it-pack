# DeriveIt — In-depth Feature Review, Edge-case Analysis & Optimization Plan

> Companion to `REVIEW.md`, `ROADMAP.md`, and `IMPLEMENTATION_PLAN.md`.
> Prepared: 2026-08-20 · Last reviewed: 2026-08-20 (review 2, post-Phase-9) ·
> Scope: `it-pack/derive-it` (`src/`), tests, and `examples/`.
> Method: source reading of the full generation pipeline (`DeriveIt`,
> `InterfaceDeriver`, `PerTraitInterfaceDeriver`, `FamilyDeriver`,
> `AdapterGenerator`, `MethodSignatureFactory`, `TypeSubstitutor`, `Naming`,
> `MethodExtractor`, filters), inspection of committed golden files
> (`tests/_expected/*.interface.php`), and reasoning about PHP reflection /
> Nette semantics.

---

## 1. Current capability snapshot (post-Phase-9)

DeriveIt derives **self-contained interfaces (and adapters)** from PHP classes via
four public modes on `DeriveIt`:

| Mode | Method | Output |
|------|--------|--------|
| Flat interface | `derive()` / `deriveInterface()` | one `InterfaceType`, no `extends` |
| Per-trait | `derivePerTrait()` / `deriveTraitInterfaces()` | one interface per trait + composed main |
| Family | `deriveFamily()` / `deriveFamilyFromRoot()` | N interfaces; cross-references rewritten via a build-time map |
| Adapter | `deriveAdapter()` | derived interface + delegating `final` adapter in one file |

Every mode is driven by `DeriveConfig` (immutable flags) and the filter chain
`Visibility → Static → Magic → Constructor`. Types are rebuilt with
`Nette\PhpGenerator\Factory::fromMethodReflection()` (the single source of truth).
The **leakage invariant** is partially asserted by
`InterfaceDeriver::assertSelfContained()` (flat mode only).

**Phase 9 (Edge-case & Optimization Hardening) is complete.** Delivered: self /
static / parent substitution (A1); default-value constant rewrite (A2);
union / intersection adapter return wrapping (A3); naming-collision detection
(A5); `failOnEmpty` (A6); `DerivationResult` (D1) + `with*()` config parity (D3) +
typed exceptions (D4); `includeConstants` / attribute + `@throws` pass-through /
`onlyMethods`+`skipMethods` (B1–B3); transitive substitution, owned-namespace
policy, root auto-discovery, combined family source (C1–C4); family + resolver
memoization (E1/E2, F4), shared `SourceRenderer` (F1), centralized `Naming` (F3).

All five quality gates are green: `test:unit` 141 tests / 258 assertions,
`cs:check` 0, `analyse` 0, deptrac 0 violations, `infection` MSI 82% (≥ 80%).

---

## 2. What is solid (do not regress)

- **Flat derivation is leakage-clean *by extends* — `assertSelfContained()` fails
  the run if the interface `extends` anything.** (Note: it does not yet audit the
  *signatures* — see §4 EC1.)
- **Type grammar fidelity** is delegated to Nette — union, intersection, nullable,
  variadic, by-ref, readonly, enum params all render correctly (golden files).
- **Per-trait** correctly de-duplicates shared method names across traits and
  composes a main interface that `extends` only the derived trait interfaces.
- **Family substitution** rewrites sibling references to derived interface FQCNs
  and supports auto-discovery from a root class.
- **Adapter generation** delegates correctly, wraps substituted compound returns
  per-segment, and handles variadic / by-ref / default values.
- **Self/static/parent substitution (A1)** resolves `self`/`static`/`parent` to the
  derived interface FQCN (default ON — a deliberate behavioral default change, see
  §5 / EC18).
- **Hardening done (Phase 8):** resolvers are instance-injected (no static state).
  **Caveat:** `FamilyDeriver` re-introduced a static cache (EC3 / §5) — flagged
  for removal in Phase 10.

---

## 3. Resolved in Phase 9 (from the original §3 analysis)

The first review (`OPTIMIZATION_PLAN.md` revision 1) listed §3.1–§3.12 as gaps.
Status after Phase 9:

| Original gap | Status | Notes |
|---|---|---|
| 3.1 self/static/parent substitution | ✅ Done (A1) | default ON |
| 3.2 default-value constant rewrite | 🟡 Partial (A2) | only when the constant's class is **mapped**; `self::CONST` and unmapped foreign refs still leak (EC6) |
| 3.3 re-expose owned interfaces | ❌ Open (A4) | still a design trade-off; tracked as G16 |
| 3.4 union/intersection adapter wrapping | ✅ Done (A3) | per-segment `match` dispatch |
| 3.5 self/static returns in adapter body | ✅ Resolved by A1 | returning the concrete adaptee (which implements the interface) satisfies the rewritten return type |
| 3.6 constants derived | ✅ Done (B1) | scalar / scalar-array only; drops objects/enum-cases silently (EC7) |
| 3.7 attributes + throws | 🟡 Partial (B2) | all-or-nothing pass-through; no owned-attribute allow-list; `#[Override]` leaks; brittle `@throws` (EC8, EC9) |
| 3.8 empty surface signal | ✅ Done (A6/D1) | `failOnEmpty` + `DerivationResult` |
| 3.9 naming-collision detection | 🟡 Partial (A5) | flat mode only; ignores enums (EC5) |
| 3.10 enum/trait/abstract targets | 🟡 Partial | discovery misses `enum_exists`; no target support matrix (EC13) |
| 3.11 includeMagic + keep-constructor | ❌ Open | documented limitation; adapter cannot forward `__construct` (EC4-adjacent) |
| 3.12 one-level substitution | ✅ Done (C1/C2) | transitive closure + owned-namespace prefix |

---

## 4. Remaining & newly-discovered edge cases / quirks (post-Phase-9)

These are concrete gaps found by reading the pipeline after Phase 9. Each lists
evidence (file:line), why it matters, and a recommended direction (cross-referenced
to a feature in §6).

### EC1 — Leakage guard is shallow (only checks `extends`)
- **Evidence:** `InterfaceDeriver::assertSelfContained()` (`InterfaceDeriver.php:222`)
  only throws when `getExtends() !== []`. A flat-derived interface whose method
  returns a **concrete sibling type not in the substitution map** (e.g.
  `Foo` when only `Bar` is being derived) passes the guard but still leaks the
  adaptee's dependency graph — directly contradicting the core invariant.
- **Why it matters:** the headline guarantee ("self-contained, nothing from the
  adaptee's hierarchy leaks") is currently unverified for signatures. Family mode
  is worse: `FamilyDeriver` derives each member via `InterfaceDeriver`, which only
  checks its own `extends`, so an unmapped sibling type in a family member leaks
  silently (EC11).
- **Recommended:** a deep leakage auditor (G1) that scans every method's return
  and parameter type strings after substitution and flags any FQCN that is
  non-builtin, outside the target namespace, and not in an allowed /
  substitution list → raise `LeakageDetectedException`.

### EC2 — Family derivation ignores the caller's `DeriveConfig`
- **Evidence:** `FamilyDeriver::deriveMember()` hard-codes
  `new DeriveConfig()` (FamilyDeriver.php:115-116), so `includeStatic`,
  `includeConstants`, `visibility`, `substituteSelfStatic`, `onlyMethods`,
  `skipMethods`, `attributePassThrough`, `throwsCapture` are **all forced to
  defaults**. `DeriveIt::deriveFamily()` / `deriveFamilyFromRoot()` accept no
  config argument.
- **Why it matters:** a user who wants a family with constants, protected methods,
  or a skip-list cannot get it — the family API silently discards their intent.
- **Recommended:** thread a `DeriveConfig` through `FamilyDeriver::derive()` and
  the facades (G2), honoring every flag per member.

### EC3 — `FamilyDeriver::$cache` is process-global mutable static state
- **Evidence:** `private static array $cache = []` (FamilyDeriver.php:33) keyed by
  `FQCN | configHash`.
- **Why it matters:** Phase 8 (H1) explicitly removed **static mutable resolver
  state** to kill cross-test contamination; this re-introduces the same class of
  bug. Within one process the cache persists across calls, can mask Infection
  mutants (a cached result from a prior mutant run satisfies a later one), and
  grows unbounded in long-running processes (IDE loops, whole-repo scans).
- **Recommended:** make the cache **injectable** (instance field, PSR-16, or a
  passed-in array) and provide `clearCache()`; never use a static property (G4).

### EC4 — `deriveAdapter` cannot satisfy static interface methods
- **Evidence:** `AdapterGenerator` emits only **instance** delegating methods
  (`AdapterGenerator.php:93-134`); `DeriveIt::deriveAdapter()` (DeriveIt.php:96)
  derives the interface using the caller's config, so `includeStatic=true` yields
  an interface with **static** methods the generated adapter does not implement →
  fatal on instantiation.
- **Why it matters:** combining adapter mode with `includeStatic` is a latent
  fatal, and there is no guard or clear error.
- **Recommended:** either emit static delegating methods that forward to the
  adaptee class (G3), or hard-error when adapter mode is requested with a config
  that produces static interface methods.

### EC5 — Collision check runs only in flat mode & ignores enums
- **Evidence:** `Naming::assertNoCollision()` is called **only** in
  `InterfaceDeriver::build()` (InterfaceDeriver.php:88); `PerTraitInterfaceDeriver`
  and `FamilyDeriver` never call it. The check itself uses `class_exists` /
  `trait_exists` (Naming.php:57) and **not** `enum_exists`.
- **Why it matters:** per-trait / family modes can silently shadow a real
  adaptee type, and a derived interface that collides with an existing enum type is
  never flagged.
- **Recommended:** run `assertNoCollision` (extended to `enum_exists`) in **every**
  mode (G12); add per-trait / family parity to the self-contained assertions.

### EC6 — `self::CONST` and unmapped foreign constant defaults leak
- **Evidence:** `TypeSubstitutor::rewriteConstantExpression()`
  (TypeSubstitutor.php:51) matches `Class::CONST`; a `self::DEFAULT` reference is
  captured with class `self`, which is not in the map → returned unchanged. And a
  foreign constant (e.g. `SimpleBackedEnum::DEFAULT`) whose class is **not** in the
  substitution map is emitted verbatim — a concrete dependency leak (contrasts with
  the type, which *is* rewritten when mapped).
- **Why it matters:** default values are the easiest place for a concrete
  dependency to slip through; the rewrite only fires on a hit.
- **Recommended:** rewrite `self::CONST` via the derived interface (G6); for
  unmapped foreign constant refs, either drop the default or keep it behind an
  `ownConstantsOnly` leakage policy.

### EC7 — Constant lifting drops non-scalar values inconsistently
- **Evidence:** `InterfaceDeriver::addConstants()`
  (InterfaceDeriver.php:166) keeps only scalar / scalar-array values; enum cases
  and other objects are silently skipped.
- **Why it matters:** an adaptee that exposes a public `const POINT = new Point()`
  simply loses the constant in the port, with no signal — an unexpected omission
  for value-object-style ports.
- **Recommended:** when `includeConstants` is on, either reject non-portable
  constants with a typed exception or document the scalar-only limitation
  explicitly (G6).

### EC8 — Attribute pass-through is all-or-nothing and leaks builtins
- **Evidence:** `MethodSignatureFactory::fromMethod()` keeps every attribute when
  `attributePassThrough` is true (MethodSignatureFactory.php:53-61, 132-139); no
  owned-attribute allow-list.
- **Why it matters:** `#[Override]` is meaningless (and invalid) on an interface
  method, and foreign attribute FQCNs are themselves a leakage surface — the exact
  concern raised in the original §3.7.
- **Recommended:** an `ownedAttributes` allow-list; always drop `#[Override]` and
  other builtins (G7).

### EC9 — `@throws` capture is brittle
- **Evidence:** `MethodSignatureFactory::applyThrows()` (MethodSignatureFactory.php:141)
  regexes the docblock; it mishandles generics in the type
  (`@throws InvalidArgumentException<Foo>`), does not FQCN-simplify, does not
  dedupe with an existing comment, and does not capture PHP 8.4 native `throws`.
- **Why it matters:** captured throws can be malformed or duplicated, and the
  native syntax is missed entirely.
- **Recommended:** robust docblock parsing + FQCN simplification + dedupe (G8).

### EC10 — Generics / `@template` and full phpdoc are dropped
- **Evidence:** only `@throws` (opt-in) is preserved; `@param` / `@return` /
  `@template` / `@deprecated` vanish from the port.
- **Why it matters:** for typed internal codebases, dropping generics and return
  docs makes the derived interface materially less useful than the source — a
  strong DX regression that the original §5 explicitly called a non-goal, but which
  is high-value for real adoption.
- **Recommended:** opt-in `preserveDocblocks` that re-emits `@param` / `@return` /
  `@throws` / `@template` / `@deprecated` with FQCN simplification (G8).

### EC11 — Per-trait & family modes skip all leakage assertion
- **Evidence:** `assertPerTraitSelfContained()` checks only that `extends` equals
  the derived trait interfaces; `FamilyDeriver` runs no leakage assertion at all.
- **Why it matters:** an unmapped sibling type in a family member or a per-trait
  method leaks silently (compounds EC1).
- **Recommended:** run the deep auditor (G1) in every mode (G12).

### EC12 — Adapter generation is flat-only (no family-of-adapters)
- **Evidence:** `DeriveIt::deriveAdapter()` derives a single class; there is no
  `deriveFamilyAdapter()`.
- **Why it matters:** deriving a family and then wanting adapters for each member
  requires manual glue; the `returnWrapperMap` already implies a family mental
  model.
- **Recommended:** `deriveFamilyAdapter(classes, wrapperMap, config)` emitting an
  adapter per family member (G14).

### EC13 — Enum / trait / interface / readonly targets only partially supported
- **Evidence:** `FamilyDeriver::discover()` checks `class_exists` /
  `interface_exists` / `trait_exists` but **not** `enum_exists`
  (FamilyDeriver.php:210); `DeriveIt::derive()` on an interface or readonly class
  is unguarded; no target support matrix or golden tests for these inputs.
- **Why it matters:** deriving an enum lifts only (dropped-by-default) static
  methods → empty surface; interfaces / readonly classes behave oddly with no
  documented contract.
- **Recommended:** add `enum_exists` to discovery + collision; document supported
  targets; add golden tests; optional `deriveFromNonInstantiable` guard (G5).

### EC14 — No rename / alias policy (B4 unimplemented)
- **Evidence:** only `onlyMethods` / `skipMethods` exist; `DeriveConfig` has no
  rename map.
- **Why it matters:** two traits exporting `save()` that must both survive cannot
  be disambiguated; the port cannot rename a method to avoid a clash.
- **Recommended:** `methodRenames` map applied uniformly across flat / per-trait`
  (G11).

### EC15 — No `analyze()` / `deriveMany()` / dry-run diff (D2 / D5 unimplemented)
- **Evidence:** `DerivationResult` exists but there is no method to obtain it
  without rendering, no batch entry point, and no "what would change" mode.
- **Why it matters:** tooling / IDE integration and CI drift detection want
  metadata and diffs, not just source strings.
- **Recommended:** `DeriveIt::analyze($class)` (no render), `deriveMany(iterable,
  config)`, and `diff($class, $existingSource)` (G9).

### EC16 — Config has no conflict validation or presets
- **Evidence:** `DeriveConfig` accepts contradictory `onlyMethods` +
  `skipMethods`, and `failOnEmpty` + a non-existent `onlyMethods` fails late / oddly;
  no `forAdapter()` / `strict()` presets or fluent builder.
- **Why it matters:** misconfiguration surfaces as confusing runtime behavior
  rather than a clear validation error.
- **Recommended:** validate flag combinations in the constructor and ship presets
  + a builder (G10).

### EC17 — Foreign types not in the map are emitted verbatim (leak)
- **Evidence:** `TypeSubstitutor::applyNamespacePrefix()` (TypeSubstitutor.php:115)
  only rewrites owned-namespace types; an outside type `App\Foo` that is not in the
  map is rendered as-is. Combined with EC1 (no signature audit) this slips through.
- **Why it matters:** a derived port that still names a concrete collaborator is
  not self-contained.
- **Recommended:** deep auditor (G1) flags every unmapped foreign FQCN; the
  owned-namespace policy (C2) plus an explicit allow-list decide what is permitted.

### EC18 — `substituteSelfStatic` default flipped to `true`
- **Evidence:** `DeriveConfig` defaults `substituteSelfStatic = true`
  (DeriveConfig.php:33), whereas the original §3.1 recommendation was "gate it and
  keep current behaviour as default until a major version."
- **Why it matters:** this is a **behavioral default change** that alters every
  golden file and surprises callers who relied on verbatim `self` / `static`.
- **Recommended:** document as a deliberate v0.x default; revisit for a major
  version (keep flag so it can be turned off). No code change required now, but it
  must be called out in the changelog / upgrade notes.

### EC19 — Per-trait same-short-name clash
- **Evidence:** `deriveTraitInterfaces()` keys the result by trait **short name**
  (PerTraitInterfaceDeriver.php:72); two traits with the same short name in
  different namespaces would overwrite each other.
- **Why it matters:** silent data loss in the returned map and a malformed composed
  interface.
- **Recommended:** key by FQCN internally; render short names only at output (G12).

### EC20 — Double reflection in family discover / derive
- **Evidence:** `FamilyDeriver::discover()` and `derive()` each `new
  ReflectionClass` for the same classes, and `discover()` calls `class_exists` on
  every discovered type (FamilyDeriver.php:170-219).
- **Why it matters:** minor perf cost repeated per family; noticeable on large
  graphs.
- **Recommended:** reuse `ReflectionClass` instances from discovery in derive;
  share one Nette `Factory` (G13).

---

## 5. Risks & non-goals (updated)

- **Static cache re-introduced (EC3 / §4).** `FamilyDeriver::$cache` contradicts
  the Phase 8 "no static mutable state" rule and can mask Infection mutants and
  leak across runs. Must be removed / made injectable in Phase 10 (G4) — this is
  the single most important maintainability fix.
- **Deliberate default change (EC18).** `substituteSelfStatic = true` by default is
  a behavioral change vs. the original recommendation. It is correct and tested,
  but must be documented as a v0.x default and revisited at a major version.
- **Deep leakage auditor (G1)** is stricter than today and may flag existing
  golden files that currently leak (e.g. a family member referencing an unmapped
  sibling). Expect to either fix those fixtures or populate the allow-list; gate
  behind a flag initially to avoid surprising existing users.
- **Re-exposing interfaces (A4 → G16)** trades strict leakage for completeness —
  must stay opt-in and allow-list scoped, never automatic.
- **Attribute pass-through (G7)** is itself a leakage surface; only pass through
  attributes the user owns.
- **Performance caching (G4 / G18)** adds memory cost and must key on config +
  class identity to avoid stale results.
- **Non-goals:** turning DeriveIt into a full refactoring/rewriter (DisenchantIt's
  domain); supporting PHP generics/templates natively (no native PHP generics —
  we only preserve phpdoc, G8); emitting implementations beyond the simple
  delegating adapter (family-of-adapters, G14, is in scope as a thin extension).

---

## 6. Brainstormed additional features (post-Phase-9 backlog)

Grouped by intent. Items marked **(core)** are correctness/leakage fixes from §4;
the rest are enhancements. IDs are stable for cross-referencing with the ROADMAP
Phase 10 steps.

### G. Correctness & leakage hardening (core)
- **G1** ✅ Deep leakage auditor — `LeakageAuditor` scans every method return/param
  type after substitution; flags non-builtin, out-of-namespace, non-allowed FQCNs;
  raises `LeakageDetectedException` (EC1, EC11, EC17). Gated behind the
  `auditLeakage` config flag (default off) so existing golden files are not broken.
- **G2** ✅ Family honors caller `DeriveConfig` — `FamilyDeriver::derive*` and the
  `DeriveIt::deriveFamily*` facades accept and forward a `DeriveConfig` (EC2).
- **G3** Adapter static-method support — emit static delegating methods, or
  hard-error when adapter + `includeStatic` combine (EC4).
- **G4** ✅ Injectable cache instead of static — `FamilyDeriver` uses an
  instance-scoped cache (constructor-injected + `clearCache()`), removing the
  process-global static that masked Infection mutants and leaked across runs (EC3).
- **G5** Full derivation-target support matrix — `enum_exists` in discovery +
  collision; document supported targets; golden tests for enum/trait/interface/
  abstract/readonly; optional `deriveFromNonInstantiable` guard (EC13).
- **G6** Complete default-value constant rewriting — `self::CONST` via derived
  interface; drop/keep policy for unmapped foreign constant refs; document scalar-
  only constant limitation (EC6, EC7).
- **G7** Attribute allow-list + builtin skip — pass through only owned attributes;
  always drop `#[Override]` and other builtins (EC8).
- **G12** ✅ Per-trait & family leakage/collision parity — `assertNoCollision`
  (now also `enum_exists`) runs in per-trait and family modes; per-trait same-short-name
  trait clash throws `NamingCollisionException` instead of silently overwriting (EC5, EC11, EC19).
- **G16** Re-expose owned interfaces (A4) — opt-in allow-list `extends` for owned
  interfaces (e.g. `Countable`), third-party kept out.

### H. Developer experience
- **G8** Robust phpdoc preservation — opt-in `preserveDocblocks`: re-emit
  `@param`/`@return`/`@throws`/`@template`/`@deprecated` with FQCN simplification +
  dedupe; handle generics (EC9, EC10).
- **G9** `analyze()` + `deriveMany()` + dry-run diff — `DeriveIt::analyze($class)`
  (no render) returning `DerivationResult`; `deriveMany(iterable, config)`;
  `diff($class, $existingSource)` for "what would change" (EC15, D2, D5).
- **G10** Config presets + validation + fluent builder — `DeriveConfig::strict()`
  / `::forAdapter()` / `::forFamily()`; constructor validation of contradictory
  flags (EC16).
- **G11** Rename / alias policy — `methodRenames` map applied uniformly across
  flat / per-trait / family (EC14, B4).
- **G14** Family-of-adapters generation — `deriveFamilyAdapter(classes,
  wrapperMap, config)` (EC12).
- **G17** CLI `bin/derive` — derive a class/file to stdout or a path for ad-hoc
  ports and dogfooding.

### I. Performance & maintainability
- **G13** Shared Nette `Factory` + de-dup reflection — pass one `Factory` through;
  reuse `ReflectionClass` instances from discovery in derive (EC20, E3).
- **G15** `BuildMeta` value object — replace the untyped `object{interface,
  methods, substitutions, constants}` returned by `InterfaceDeriver::build()` with
  a typed `BuildMeta`; extract a shared deriver base (seeding the substitutor +
  `shouldSkipMethod`) to remove duplication between `InterfaceDeriver` and
  `PerTraitInterfaceDeriver`.
- **G18** PSR-6/16 cache adapter for reflection — optional cache so whole-repo
  scans don't re-reflect (E5).

---

## 7. Recommended sequencing (Phase 10)

1. **Deepen correctness first (G1, G12, G2, G3, G4).** The deep leakage auditor
   (G1) plus per-mode parity (G12) make the core invariant *real* (not just
   `extends`). Family-config threading (G2) and the static-cache removal (G4) fix
   the two most surprising current behaviors. Adapter static support (G3) removes a
   latent fatal.
2. **Target robustness (G5, G6, G7).** Enum/interface support matrix (G5), complete
   constant rewriting (G6), owned-attribute allow-list (G7).
3. **DX (G8, G9, G10, G11, G14, G17).** phpdoc preservation (G8) is the highest-DX
   win for typed codebases; metadata/diff APIs (G9); presets (G10); rename (G11);
   family-of-adapters (G14); CLI (G17).
4. **Feature (G16).** Re-expose owned interfaces — opt-in, allow-list scoped.
5. **Performance & maintainability (G13, G15, G18).** Shared factory (G13),
   `BuildMeta` refactor (G15), optional PSR cache (G18).

Each item is small enough to land as its own PR with golden/snapshot tests and
must keep all five gates green (never let `test:unit`, `cs:check`, `analyse`,
`architecture`, or `infection` regress — per `AGENTS.md`).
