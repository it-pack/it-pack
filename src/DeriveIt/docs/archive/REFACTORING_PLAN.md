# DeriveIt — Refactoring & Modernization Plan

> Prepared: 2026-08-22 · Implements the findings register in `REVIEW.md` (F1–F13).
> Companion to `ROADMAP.md` Phase 11 (execution spec) and `REVIEW.md` (audit).
> Every step must keep all five gates green (`test:unit`, `cs:check`, `analyse`,
> `architecture`, `infection` ≥ 80%) per `AGENTS.md`.

## Objective

Turn the "sound but structurally uneven" verdict of `REVIEW.md` into an
"exemplary" one without changing user-visible behavior:

- Remove the largest DRY liability (the 17 duplicated `DeriveConfig` withers, F1).
- Close the hexagonal gap: secondary derivers behind interfaces + a real factory
  at the composition root (F2).
- Eliminate magic strings/numbers and the last two `else`/`elseif` (F4, F5, F8,
  F9, F10).
- Refresh drifted docs and leverage modern PHP (enums, property hooks, lazy
  objects) where it pays off (F3, F4, F12).

**Out of scope:** behavior changes to derivation output, new derivation modes,
or new public APIs beyond the factory/enum additions below. Where an enum
introduces a type change to a public signature (`DeriveConfig::$visibility`,
`DeriveCliInput::$mode`), it is treated as a controlled API change with a
backward-compatible shim where cheap.

---

## Sequencing

```
Quick wins (no API change, low risk):
  F7  delete duplicate @param in DeriveConfig
  F8  0o755 const + drop @ suppressor in DeriveCommand
  F9  hoist VENDOR_PREFIXES / CACHE_KEY_PREFIX consts
  F10 remove last else / elseif in DeriveCommand
  F11 reuse SourceRenderer in DeriveIt::renderInterface

Structural (API-stable internal refactors):
  F1  collapse DeriveConfig withers via copyWith()
  F6  pass effective substitution map into BuildMeta

Type-safety / hexagonal (controlled API changes):
  F4  DeriveMode + Visibility enums
  F5  split DeriveCommand::parse() + declarative option table
  F2  FamilyDeriverInterface + AdapterGeneratorInterface + DeriveItFactory

Docs / optional:
  F3  refresh docs/guide/architecture.md
  F12 optional lazy-object adapter
  F13 document CLI trust model
```

---

## Step-by-step tasks

### R.1 — `DeriveConfig` wither collapse (F1, High)

**Goal:** eliminate ~450 lines of duplication across 17 `with*()` methods without
changing the public fluent API or the `readonly` guarantee.

**Approach**
1. Add a private helper to `src/Generator/DeriveConfig.php`:
   ```php
   private function copyWith(array $overrides): self
   {
       $vars = get_object_vars($this);
       foreach ($overrides as $key => $value) {
           $vars[$key] = $value;
       }
       return new self(...$vars);
   }
   ```
   (Reading current state via `get_object_vars($this)` + re-constructing keeps the
   class `readonly` and preserves constructor validation.)
2. Replace each `with*()` body with a one-liner, e.g.:
   ```php
   public function withVisibility(string $visibility): self
   {
       return $this->copyWith(['visibility' => $visibility]);
   }
   ```
3. Keep `withTypeSubstitutionMap()` etc. exactly as-is in name/signature so callers
   and tests are unaffected.
4. Re-run `composer test:unit` — the golden/snapshot tests must be byte-identical.

**Acceptance:** `DeriveConfig.php` drops from ~590 to ~180 lines; all 17 methods
remain; `cs:check`, `analyse`, `infection` unchanged. **No public API change.**

**Alternative (future major, noted not done):** PHP 8.4 property hooks
(`public string $visibility { set => $value; }`) could make each property a
virtual fluent setter, but interacts awkwardly with `readonly` + constructor
validation; defer to a v2.

---

### R.2 — Secondary deriver interfaces + `DeriveItFactory` (F2, Med)

**Goal:** make "a deriver" polymorphic across all four modes and provide a
composition-root factory that assembles derivers (optionally sharing a
`ReflectionCacheInterface` and a Nette `Factory`).

**Approach**
1. New `src/Contract/Generator/FamilyDeriverInterface.php`:
   `derive(array, array=[])`, `deriveFromRoot(string, array=[])`,
   `deriveSource(array, array=[]): string`, `clearCache()`.
2. New `src/Contract/Generator/AdapterGeneratorInterface.php`:
   `generate(): Nette\PhpGenerator\ClassType`, `generateSource(): string`.
3. `FamilyDeriver` implements `FamilyDeriverInterface`;
   `AdapterGenerator` implements `AdapterGeneratorInterface`. No behavior change.
4. New `src/DeriveItFactory.php` (placed in the **Entry** layer so deptrac stays
   green — Entry may depend on Generator + Contract). Constructor accepts
   `DeriveConfig`, optional `?ReflectionCacheInterface`, optional `?Nette\PhpGenerator\Factory`.
   Exposes `interfaceDeriver()`, `perTraitDeriver()`, `familyDeriver()`,
   `adapterGenerator(InterfaceType, ReflectionClass, array)` — each `new`-ing the
   concrete with the shared cache/factory injected.
5. Optionally have `DeriveIt` facade delegate to a default `DeriveItFactory` (BC:
   keep the existing static methods; they may internally use
   `new DeriveItFactory(...)`).
6. Update `deptrac.yaml` only if a new layer is introduced; placing the factory in
   Entry requires **no** deptrac change (Entry already depends on Generator).

**Acceptance:** a test builds all four derivers through `DeriveItFactory` with a
shared `InMemoryReflectionCache` and asserts identical output to the direct path;
`architecture` stays 0 violations. **Controlled API addition** (new interface +
factory), no removal.

**Note:** this is the explicit factory/ports ask in the review; it also unblocks a
future "shared cache across the whole CLI run" optimization.

---

### R.3 — Refresh drifted architecture docs (F3, Med)

**Goal:** `docs/guide/architecture.md` must match shipped code.

**Approach**
1. Replace the `ClassReflection::setRecursive*Resolver()` wording with the
   instance-injection design (`src/Reflection/ClassReflection.php` constructor /
   `create()` inject resolvers; single immutable default).
2. Update the layer diagram to include the **Cli** layer (`src/Cli/*`) and its
   allowed deps; note `Entry` → `Cli` direction.
3. Correct the "All three derivers implement `DeriverInterface`" sentence: state
   that `InterfaceDeriver` + `PerTraitInterfaceDeriver` implement `DeriverInterface`
   and that `FamilyDeriver`/`AdapterGenerator` are covered by R.2 interfaces.
4. Add a one-line note that `DeriveIt` is a static facade over injectable derivers
   (links to R.2 factory).

**Acceptance:** `docs/guide/architecture.md` no longer references removed APIs.

---

### R.4 — `DeriveMode` + `Visibility` enums (F4, Med)

**Goal:** remove the bare mode/visibility string literals flagged as magic strings.

**Approach**
1. New `src/Cli/DeriveMode.php` (or `src/Generator/DeriveMode.php` — put in
   `Generator` so both Cli and Config can depend; **no deptrac change** since Cli
   depends on Generator):
   ```php
   enum DeriveMode: string {
       case FLAT = 'flat';
       case PER_TRAIT = 'per-trait';
       case ADAPTER = 'adapter';
       case FAMILY = 'family';
       case FAMILY_ADAPTER = 'family-adapter';
   }
   ```
2. New `src/Generator/Visibility.php`:
   ```php
   enum Visibility: string {
       case PUBLIC = 'public';
       case PROTECTED = 'protected';
   }
   ```
3. `DeriveConfig`: change `visibility` param/property type to `Visibility`
   (keep `VISIBILITY_PUBLIC`/`VISIBILITY_PROTECTED` string constants as
   backward-compat aliases that map to the enum, or remove and update the 2
   internal call sites in `DeriveCommand::buildConfig`). Validation constructor
   becomes `in_array($visibility, Visibility::cases(), true)`-free (enum guarantees
   validity).
4. `DeriveCliInput::$mode`: type `DeriveMode`. `DeriveCommand` uses `DeriveMode`
   in `validate()`/`runDerive()`; the `valueOptions`/`switch` in `parse()` maps CLI
   tokens → `DeriveMode` (ties into R.5).
5. Keep `DeriveConfig::visibilityMask()` returning the `ReflectionMethod` bitmask
   via a `match` on the enum.

**Acceptance:** no bare `'flat'`/`'public'` literals remain in `src/` outside enum
`value` declarations; `cs:check`, `analyse` green; CLI behavior identical.

---

### R.5 — Split `DeriveCommand::parse()` + declarative options (F5, Med)

**Goal:** cut the highest-complexity method (CC ≈ 30) and make adding a mode a
single-site change (OCP).

**Approach**
1. Extract a small `DeriveCliInput ArgParser` (same `Cli` layer) that holds a
   declarative table:
   ```php
   private const OPTIONS = [
       'mode' => ['value' => true,  'map' => fn (string $v) => DeriveMode::from($v)],
       'out'  => ['value' => true,  'map' => fn (string $v) => $v],
       'include-static' => ['value' => false, 'flag' => fn () => $cfg->withIncludeStatic(true)],
       // ...
   ];
   ```
   This replaces the inline `valueOptions` array + 25-case `switch`.
2. `DeriveCommand::parse()` becomes a thin loop over `ArgParser::parse($args)`.
3. Mode dispatch in `runDerive()` switches on `DeriveMode` enum (already an enum
   after R.4), so the allowed-mode list lives in exactly one place (the enum).
4. Keep exit codes (0/1/2/3) and error messages identical.

**Acceptance:** `DeriveCommandTest` still passes (13 tests); CC of the parser drops
substantially; adding a mode = add enum case + one option row.

---

### R.6 — Family map in `DerivationResult` (F6, Low)

**Goal:** `DerivationResult::$substitutions` reflects the real effective map in
family mode.

**Approach**
1. In `InterfaceDeriver::build()` (called via `deriveWithSubstitutor`), accept the
   effective substitution map (the `$map` passed to `TypeSubstitutor`) and put it in
   `BuildMeta::$substitutions` instead of only `config->typeSubstitutionMap` +
   self/static/parent.
2. `deriveWithResult()`/`analyze()` propagate the new `BuildMeta` field unchanged.

**Acceptance:** a family derivation through `deriveWithResult()` reports the full
family cross-reference map; flat mode output unchanged.

---

### R.7 — Delete duplicate `@param` (F7, Low)

**Goal:** remove the copy-paste doc defect.

**Approach:** in `src/Generator/DeriveConfig.php` delete the duplicate
`@param list<string> $reExposeOwnedInterfaces …` line (lines 36–37).

**Acceptance:** `phpstan`/IDE no longer sees a duplicated annotation.

---

### R.8 — `0o755` const + drop `@` (F8, Low)

**Goal:** remove magic `0o777` + error suppression.

**Approach:** in `src/Cli/DeriveCommand.php` add
`private const OUTPUT_DIR_MODE = 0o755;` and replace the two
`!@mkdir($p, 0o777, true) && !is_dir($p)` with `!mkdir($p, self::OUTPUT_DIR_MODE, true) && !is_dir($p)`
(the trailing `!is_dir($p)` re-check already guards failure).

**Acceptance:** `cs:check` + `analyse` green; directory creation still works
(cover with the existing `--out=DIR/` CLI test).

---

### R.9 — Hoist magic literals to constants (F9, Low)

**Goal:** remove buried magic arrays/strings.

**Approach**
1. `src/Generator/FamilyDeriver.php`: extract
   `private const VENDOR_PREFIXES = ['Nette\\','PHPUnit\\','Codeception\\','Symfony\\','Composer\\','Illuminate\\'];`
   used by `isUserland()`.
2. `src/Reflection/PsrCacheReflectionCache.php`: extract
   `private const CACHE_KEY_PREFIX = 'deriveit.reflection.';` used in `key()`.

**Acceptance:** no behavior change; literals now named.

---

### R.10 — Remove last `else`/`elseif` (F10, Low)

**Goal:** reach full `else`/`elseif`-free `src/`.

**Approach**
1. `src/Cli/DeriveCommand.php:143` — convert the `elseif` value-option branch into
   the preceding `if` returning/continuing (guard clause) or fold into the
   `ArgParser` table from R.5.
2. `src/Cli/DeriveCommand.php:396` — replace `} else { $fqcn = $target; }` with an
   early default assignment + guard clause:
   ```php
   $fqcn = $target;
   if (is_file($target) && (str_ends_with($target, '.php') || str_contains($target, '/'))) {
       $fqcn = $this->classFromFile($target);
   }
   ```

**Acceptance:** grep for `else`/`elseif` in `src/` returns zero code matches.

---

### R.11 — Reuse `SourceRenderer` (F11, Low)

**Goal:** remove duplicated PhpNamespace+PhpFile assembly.

**Approach:** replace `DeriveIt::renderInterface()` (`src/DeriveIt.php:222`) with a
call to `SourceRenderer::render($interface, $namespace)`; delete the private method.

**Acceptance:** rendered output byte-identical (covered by existing derive tests).

---

### R.12 — Optional lazy-object adapter (F12, Low/optional)

**Goal:** offer a lazily-initialised adaptee via PHP 8.4 lazy objects.

**Approach (experimental, gated behind a flag)**
1. Add `AdapterGenerator::withLazyAdaptee()` (or a `DeriveConfig` option) that,
   instead of a directly-injected concrete, builds the adaptee through
   `ReflectionClass::newLazyGhost()` / `newLazyProxy()` so construction is deferred
   to first delegated call.
2. Guard: only when the adaptee class is not `final`/not disallowed by lazy-object
   rules; fall back to eager injection otherwise.
3. Ship behind an opt-in so default output is unchanged (keeps golden files valid).

**Acceptance:** golden tests unaffected by default; a new test proves the lazy
adapter forwards correctly. **Mark optional** — may slip to post-v1.

---

### R.13 — Document CLI trust model (F13, Info)

**Goal:** make the "run only on trusted code" expectation explicit.

**Approach**
1. Add one line to `DeriveCommand::writeHelp()` noting the CLI autoloads
   operator-supplied class names.
2. Add a sentence to `docs/guide/usage.md` under the CLI section.

**Acceptance:** help text + guide state the trust model.

---

## Risks & mitigations

- **Enum signature change (R.4)** touches `DeriveConfig` (public) and
  `DeriveCliInput` (internal). Mitigation: keep string constants as aliases /
  accept both in the constructor for one minor release; update the 2 call sites in
  `DeriveCommand::buildConfig`.
- **`copyWith()` via `get_object_vars` (R.1)** relies on constructor param order
  matching property names — true today. Mitigation: a unit test asserts
  `withX()` results equal the previous hard-coded reconstruction for every flag.
- **deptrac** — all new types (`DeriveMode`, `Visibility`, `DeriveItFactory`,
  interfaces) are placed in layers already allowed to depend on each other (Cli→
  Generator→Contract; Entry→Generator). No `deptrac.yaml` change expected; verify
  after R.2/R.4.
- **Infection** — R.1/R.11 are pure refactors; ensure mutants that would have been
  killed by the old duplication are still killed (the new `copyWith` path is
  exercised by every `with*()` test).

## Implementation status (2026-08-22)

Implemented in the Phase 11 working pass (all five gates green: `test:unit` 229
tests, `cs:check` 0, `analyse` 0 errors, `architecture` 0 violations; `infection`
≥ 80% MSI — see run):

- ✅ **R.1** — `DeriveConfig` withers collapsed via `copyWith()` (18 withers, ~590 →
  ~200 lines; no public-API change). `tests/Unit/Generator/DeriveConfigTest` style
  checks still pass.
- ✅ **R.2** — `FamilyDeriverInterface` + `AdapterGeneratorInterface` added
  (Contract/Generator); `FamilyDeriver` / `AdapterGenerator` implement them.
  New `DeriveItFactory` (Entry) assembles all four derivers behind their
  interfaces, sharing an optional cache + Nette `Factory`; covered by
  `tests/Unit/DeriveItFactoryTest`.
- ✅ **R.3** — `docs/guide/architecture.md` refreshed (Cli layer, instance-injected
  resolvers, corrected deriver-interface sentences, facade-over-factory note).
- ✅ **R.4** — `Visibility` + `DeriveMode` enums (Generator). `DeriveConfig` and
  `DeriveCliInput`/`DeriveCommand` use them; BC preserved (`VISIBILITY_*` constants
  are now enum cases; `DeriveConfig` accepts `string|Visibility`; invalid visibility
  still throws `InvalidArgumentException`).
- ✅ **R.5** — `DeriveCommand::parse()` split into a declarative `ArgParser` +
  `ParseState`; the 25-case switch is gone and the allowed-mode list lives only in
  the `DeriveMode` enum (OCP). `DeriveCommandTest` (13 tests) still passes.
- ✅ **R.6** — `InterfaceDeriver::build()` now reports the *effective* substitutor
  map via `TypeSubstitutor::getMap()`, so family derivation metadata carries the
  full cross-reference map; flat-mode metadata is unchanged in behavior.
- ✅ **R.7** — duplicate `@param` removed from `DeriveConfig`.
- ✅ **R.8** — `DeriveCommand::OUTPUT_DIR_MODE = 0o755` const; `@mkdir` suppressor
  dropped.
- ✅ **R.9** — `FamilyDeriver::VENDOR_PREFIXES` and
  `PsrCacheReflectionCache::CACHE_KEY_PREFIX` constants hoisted.
- ✅ **R.10** — last `else`/`elseif` removed; `grep` for `else`/`elseif` in `src/`
  is now clean.
- ✅ **R.11** — `DeriveIt::renderInterface()` replaced by `SourceRenderer`.
- ✅ **R.13** — CLI trust model documented in `DeriveCommand::writeHelp()` and
  `docs/guide/usage.md`.

**Deferred (optional, post-v1):**

- ⏸️ **R.12** — lazy-object adapter. Marked optional in the plan; not implemented
  this pass. The generated adapter is a *rendered source string*, so a runtime
  `newLazyGhost()`/`newLazyProxy()` adaptee would belong to a separate runtime
  adapter variant, not the generated code. Left for a later minor with a concrete
  use case.

## Definition of Done (Phase 11)

- [x] F1: `DeriveConfig` withers collapsed (`copyWith`); lines ~590→~200.
- [x] F2: `FamilyDeriverInterface` + `AdapterGeneratorInterface` + `DeriveItFactory`
      added; derivers implement them.
- [x] F3: `docs/guide/architecture.md` matches shipped code.
- [x] F4: `DeriveMode` + `Visibility` enums; no bare mode/visibility literals in `src/`.
- [x] F5: `DeriveCommand::parse()` split; mode addition is single-site.
- [x] F6: family map present in `DerivationResult::$substitutions`.
- [x] F7–F11: doc dup removed, `0o755` const, hoisted consts, zero `else`/`elseif`,
      `SourceRenderer` reused.
- [x] F13: CLI trust note documented. (F12 lazy adapter deferred — optional, post-v1.)
- [x] All five gates green on PHP 8.4 & 8.5 (verified this pass: `test:unit` 229,
      `cs:check` 0, `analyse` 0 errors, `architecture` 0 violations, `infection`
      ≥ 80% MSI).

*Companion documents: `REVIEW.md` (audit + findings F1–F13), `ROADMAP.md` Phase 11
(execution spec).*
