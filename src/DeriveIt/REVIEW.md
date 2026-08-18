# DeriveIt — Project Review

> Review date: 2026-08-14
> Scope: `/home/quill/tinker/php/project/it-pack/src/DeriveIt`
> Method: source reading, dependency analysis, running the project's own QA tooling (PHPUnit/Codeception, PHP-CS-Fixer, PHPStan).

---

## 1. Summary

DeriveIt (`it-pack/derive-it`) is the **second package of the "It-Pack" family**, sitting alongside `ConfigureIt`. Its stated goal is to *derive interfaces and adapters from third-party or legacy PHP classes* — walking a class's full parent/trait/interface graph and flattening it into a clean, self-contained port you own.

**Current reality vs. ambition:** What is implemented in `src/` is the **graph-walking layer** — three recursive resolvers plus a thin `ClassReflection` wrapper — **and**, since Phase 1, a **flat interface deriver** (`DeriveIt::derive($class)` → a self-contained Nette `InterfaceType`/source; see `src/Generator/*`). The deriver drops constructors, static methods (opt-in), and magic methods by default, and emits no `extends`/trait `use` leaking the adaptee hierarchy. The actual **adapter generation** and the **per-trait / type-substitution modes** (the rest of the README's value proposition) remain roadmap items (Phases 3–5). README "What it does" over-states those remaining capabilities.

**Health at a glance:**

| Check | Command | Result |
|-------|---------|--------|
| Unit tests | `codecept run Unit` | ✅ 27/27 pass, 27 assertions |
| Coding standard | `php-cs-fixer fix --dry-run` | ✅ 0 files to fix |
| Static analysis | `phpstan analyse` (level 5) | ❌ **15 errors** — fails |
| Declared PHP version | composer.json | ❌ **none** (code needs PHP ≥ 8.4) |

The package is **early and structurally incomplete**, but the existing core is small, coherent, and well-tested. The biggest risk is not code quality — it is the **gap between documented intent and delivered functionality**.

---

## 2. Repository Structure

```
DeriveIt/
├── composer.json / composer.lock     # deps, autoload, QA scripts
├── phpstan.neon                      # level 5, paths: src + tests/{Acceptance,Functional,Unit}
├── .php-cs-fixer.php                 # PSR-12 + @PHP84Migration ruleset
├── codeception.yml                   # suites: Unit, Functional, Acceptance
├── README.md                        # describes the full vision
├── src/
│   ├── Reflection/ClassReflection.php        # public facade (extends ReflectionClass)
│   ├── Resolver/
│   │   ├── RecursiveClassResolver.php        # walks parent classes
│   │   ├── RecursiveInterfaceResolver.php    # walks interfaces (incl. interface inheritance)
│   │   └── RecursiveTraitResolver.php        # walks traits (incl. nested traits)
│   ├── Contract/Resolver/                    # 4 interfaces (1 base + 3 typed)
│   └── Behavior/Utility/LazyCreatable.php    # trait: create() + createLazy() proxy factory
├── scripts/
│   ├── generate_reflection_adapter.php       # scratch generation experiment (broken)
│   └── create_text_constructs.php            # empty stub (10 lines)
├── fixtures/
│   ├── Stubs/                                 # 21 files — used by unit tests
││   └── *.php (60+ files)                      # unused — future generation fixtures
├── tests/
│   ├── Unit/                                  # the only suite with real tests
│   ├── Functional/  (empty — config only)
│   └── Acceptance/  (empty — config only)
└── generated/                                # empty — no output is ever produced
```

---

## 3. What Is Actually Implemented

### 3.1 The resolver layer (the real deliverable so far)

Three classes implement the `RecursiveResolverInterface` (`resolve(ReflectionClass): array`):

- **`RecursiveClassResolver`** — `src/Resolver/RecursiveClassResolver.php:20`. Recurses up `getParentClass()`, collecting `[self, …parent, …grandparent]`. Linear chain. Correct.
- **`RecursiveInterfaceResolver`** — `src/Resolver/RecursiveInterfaceResolver.php:20`. For the class **and each ancestor**, collects `getInterfaces()` and recurses into each interface's own `getInterfaces()`. Relies on PHP's `array_merge` **string-key de-duplication** (interface names as keys) to avoid duplicates. Verified empirically: for a 4-level hierarchy (`ClassD`) it returns exactly 6 unique `ReflectionClass` objects with no duplicates.
- **`RecursiveTraitResolver`** — `src/Resolver/RecursiveTraitResolver.php:20`. Collects traits per class up the chain *and* recurses into each trait's own used traits. Uses `array_unique` on top. Correct but does **redundant re-walking** (a parent's full resolution is recomputed each level — acceptable at this scale, see §6).

All three `use LazyCreatable` and expose a `create(): self` factory.

### 3.2 The facade

**`ClassReflection extends ReflectionClass`** (`src/Reflection/ClassReflection.php:15`) adds:
- `create(object|string): self` — constructor wrapper.
- `getClassesRecursively()` / `getInterfacesRecursively()` / `getTraitsRecursively()` — instance methods that delegate to the three resolvers.
- Three `set*Resolver()` / `get*Resolver()` pairs holding **static, mutable singleton state**.

### 3.3 Tests

`tests/Unit/` contains 4 test classes, **27 passing tests**, all green. They cover:
- Stub-hierarchy architecture (A→B→C→D with traits/interfaces).
- Each resolver's output against expected class/interface/trait name lists.
- `ClassReflection` delegating to injected stub resolvers (using `Codeception\Stub::makeEmpty`).
- `LazyCreatable::createLazy()` returns a proxy instance.

The **Acceptance and Functional suites are empty** (config + support classes only). `tests/Support/Data` is empty (`.gitkeep`).

---

## 4. Status of QA Tooling

### 4.1 Unit tests — ✅ PASS
`codecept run Unit` → **OK (27 tests, 27 assertions)**.

### 4.2 Coding standard — ✅ PASS
`php-cs-fixer fix --dry-run --diff` → **0 files to fix**. Note: PHP-CS-Fixer prints a warning: *"No PHP version requirement found in composer.json"*.

### 4.3 Static analysis — ❌ FAIL
`phpstan analyse` (level 5) → **[ERROR] Found 15 errors**. Breakdown:

- **Real issues in `src/` (5):**
  - `src/Reflection/ClassReflection.php:33, :49, :65` — `ternary.shortNotAllowed`: the short ternary `self::$x ?: self::$x = …` is forbidden by the active ruleset. Should be `self::$x ??= …`.
  - `src/Resolver/RecursiveTraitResolver.php:24` — `empty.notAllowed`: `empty($traits)` must be a strict comparison.
  - `src/Behavior/Utility/LazyCreatable.php:13` — `shipmonk.deadMethod`: `createLazy()` flagged as unused.
- **False positives in `tests/` (10):** All `provide*Data()` methods are flagged `method.unused` / `shipmonk.deadMethod`. They **are** consumed at runtime via Codeception's `#[DataProvider('…')]` attribute, but the static-analysis extension does not recognize that usage. These should be suppressed/whitelisted through the Codeception-aware extension config, not "fixed" by deleting real data providers.

**Conclusion:** `composer analyse` is currently **red**. The `src/` errors are trivially fixable; the `tests/` errors are config noise.

---

## 5. Gap Analysis: README Claims vs. Delivered Code

The README ("What it does", lines 13–20) promises far more than exists:

| README claim | Status in code |
|---|---|
| Walk full inheritance chain (parents) | ✅ `RecursiveClassResolver` |
| Walk trait usage incl. nested traits | ✅ `RecursiveTraitResolver` |
| Walk already-implemented interfaces | ✅ `RecursiveInterfaceResolver` |
| **Flatten into method signatures** | ✅ `InterfaceDeriver` (Phase 1) |
| **Filter by visibility (public only)** | ✅ default public; static/magic/ctor flags (Phase 1/2) |
| **Handle static vs. instance methods** | ✅ default drops static; opt-in `includeStatic` (Phase 1) |
| **Drop constructors** | ✅ `__construct`/`__destruct` dropped by default (Phase 1) |
| **Render clean interface via `nette/php-generator`** | ✅ `DeriveIt::derive()` (Phase 1) |
| **Interface-per-trait option** (roadmap) | ❌ Only 1 flat resolver path |
| **Type substitution map** (roadmap) | ❌ Not started |
| **OTel / LLM-assisted discovery** (roadmap) | ❌ Not started |

The README's "Usage sketch" (`src/Reflection/ClassReflection.php:24-34`) stops at *"→ feed the resolved graph into the interface/adapter generation step"* — **that step does not exist**. There is no class in `src/` that accepts the resolved arrays and emits an `InterfaceType`/`ClassType`.

### 5.1 The "generation step" was a broken scratch file (now retired)
`scripts/generate_reflection_adapter.php` (removed in Phase 1.7) was a development
experiment, not a deliverable: it had an early `exit;` after which lines were
unreachable, a second `return` that could never execute, and mixed/unused Nette
imports. It has been replaced by the real deriver (`src/Generator/InterfaceDeriver.php`
+ `DeriveIt::derive()`), with `scripts/derive.php` as a thin demo.

### 5.2 Fixtures & test slots
- The **~60 root fixtures** (`fixtures/ReadonlyClass.php`, `UnionTypeClass.php`,
  `IntersectionTypeClass.php`, `ConsumesEnumParamClass.php`, `VariadicAndByRefClass.php`,
  `SelfStaticParentReturnClass.php`, etc.) are now exercised by the golden-file snapshot
  test (`tests/Unit/Generator/InterfaceDeriverSnapshotTest.php`) over
  `tests/_expected/*.interface.php` — they are no longer dead weight.
- `generated/` remains empty — derivation renders in-memory / to string (no file writes).
- `create_text_constructs.php` is an **empty stub** (10 lines, only imports).
- Acceptance/Functional suites still have **zero tests** (planned in Phase 6).

---

## 6. Code-Quality Findings

### 6.1 Real defects / smells
1. **PHPStan failures in `src/`** (§4.3): short-ternary (×3) and `empty()` usage. Easy fixes.
2. **No `require.php` constraint** in `composer.json` (§1, §4.2 warning). The code uses **PHP 8.4+** features — `ReflectionClass::newLazyProxy()` in `LazyCreatable::createLazy()` (`src/Behavior/Utility/LazyCreatable.php:15`) and the `@PHP84Migration` CS ruleset. Installing on PHP < 8.4 will fail at runtime. A `{"require": {"php": ">=8.4"}}` (or appropriate) constraint is missing.
3. **Global mutable static state** in `ClassReflection` (`src/Reflection/ClassReflection.php:17-19, 31-72`). Resolvers are stored as `static` singletons mutated via `set*Resolver()`. This is test-hostile: state leaks across test cases and is **never reset**. The unit tests happen to set-and-forget, but any suite ordering or parallel run could observe cross-test contamination. A constructor-injected or per-instance resolver would be safer.
4. **`LazyCreatable` proxy is decorative.** Resolvers call `create()` (eager), not `createLazy()` (proxy). `createLazy()` is only exercised by one test and is not part of any production path — so the "lazy" optimization buys nothing. Either wire it in or drop it to avoid the misleading "Lazy" name.
5. **Return type is `array` but values are implicitly keyed-by-class-name.** `RecursiveInterfaceResolver` (and, via `array_merge`, the others) returns an **associative array keyed by class/interface name**, relying on `array_merge` string-key semantics for de-duplication. This is undocumented and fragile: a naive consumer doing `array_values()`/`count()` gets correct numbers by luck, and any change to the merge strategy could silently introduce duplicates. The contract should state the shape (or return a de-duplicated list explicitly).
6. **Redundant recursion in `RecursiveTraitResolver`** (`src/Resolver/RecursiveTraitResolver.php:36-43`): `resolveClassInheritance()` calls the *full* `resolve()` on each parent, then `resolve()` again re-merges `resolveTraitInheritance()`. Correct, but re-walks shared ancestors. Fine now; worth noting before hierarchies grow.
7. **`ClassReflection extends ReflectionClass`** is an unusual inheritance choice (rather than composition). It works and gives free access to all `ReflectionClass` methods, but it also exposes the *entire* reflection API as if it were "DeriveIt", which may confuse the public surface. Composition + a private `ReflectionClass` would be a cleaner boundary.

### 6.2 Dependency / packaging observations
8. **Codeception modules in `require` (production), not `require-dev`** — `composer.json:6-7` lists `codeception/module-phpbrowser` and `codeception/module-asserts` as runtime requirements. A consuming library should not pull a browser test framework into production. These belong in `require-dev`.
9. **`nikic/php-parser` declared as a direct dependency** (`composer.json:5`, `^5.7`) but is **not used directly** anywhere in `src/` or `tests/` — it is only a transitive dependency of `nette/php-generator`. It can be dropped from direct `require`. (Nette *is* used, but only in the scratch script, not `src/`.)
10. **Mismatched statuses between packages.** The sibling `ConfigureIt` package has substantial source, docs (`CHANGELOG.md`, `IMPLEMENTATION_PLAN.md`, ADRs) and an active git history. DeriveIt, by contrast, is at the "initial scaffolding" commit stage (`82212d0 Add initial source and tests`) with only minor follow-ups.

---

## 7. Recommendations (priority order)

**P0 — Make the project's own tooling green**
- Fix the 3 short-ternary sites in `ClassReflection` → `??=`.
- Replace `empty($traits)` in `RecursiveTraitResolver:24` with a strict `=== []` / `count()` check.
- Configure PHPStan to recognize Codeception `#[DataProvider]` (the `phpa`/`shipmonk` Codeception extension) so the 10 `tests/` "deadMethod" errors are whitelisted, or annotate them. Goal: `composer analyse` exits 0.

**P1 — Close the documentation/implementation gap**
- Either **implement the generation step in `src/`** (visibility filtering, constructor dropping, `nette/php-generator` rendering, interface-per-trait option) — which is the actual product — or **rewrite the README "What it does"** to describe only what exists, and move the rest explicitly under "Roadmap / Not yet implemented". The current README over-promises.
- Promote `scripts/generate_reflection_adapter.php` into a real, tested generator (or delete it). Remove its dead code (`exit;` + unreachable lines 161–180; double `return` at 37).

**P2 — Packaging hygiene**
- Add `"php": ">=8.4"` to `composer.json` `require`.
- Move Codeception modules to `require-dev`.
- Drop `nikic/php-parser` from direct `require` (keep `nette/php-generator`).
- Document the resolver return shape (keyed/de-duplicated array) in the contracts.

**P3 — Design hardening (when scaling up)**
- Replace static mutable resolver singletons with constructor/instance injection + a reset path, removing cross-test state leakage.
- Consider composition over `extends ReflectionClass` for a cleaner public boundary.
- Wire `LazyCreatable::createLazy()` in or remove it.
- Add a small Acceptance/Functional smoke test once the generator exists, and start using the `generated/` output in CI.

---

## 8. Conclusion

DeriveIt is a **small, clean, well-tested kernel** (graph-walking resolvers) wrapped in a **README that describes a much larger, not-yet-built product**. The foundation is sound and the unit tests are green, but:

- The headline feature — turning a resolved graph into a derived interface/adapter — **does not exist in `src/`**.
- **Static analysis is failing** on the shipped code (trivial fixes).
- **Packaging is incomplete** (no PHP version constraint; test deps in production; unused dep).
- ~60 fixtures and an empty `generated/` dir signal **unfinished scaffolding** rather than a finished tool.

It is accurately "Early, active" (per README:46), but the README's *capability* section should not be read as implemented. The fastest path to a credible v0.1 is: (a) make `composer analyse` pass, (b) ship a minimal real generator for the `Stubs/` fixtures, and (c) align the README with reality.
