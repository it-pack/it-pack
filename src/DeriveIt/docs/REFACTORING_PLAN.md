# DeriveIt — Refactoring Plan (REVIEW.md F-1 … F-15)

> Companion to `docs/REVIEW.md` (2026-08-24 audit). Prepared: 2026-08-24 ·
> Package: `it-pack/derive-it` · Scope: every finding in `docs/REVIEW.md` §11,
> organized into five milestones (A–E) with individual, individually-verifiable
> steps `R3-1 … R3-15`.
>
> **Hard rule:** every step must keep all five quality gates green
> (`composer test:unit`, `cs:check`, `analyse`, `architecture`,
> `infection ≥ 80%`) per `AGENTS.md`. Baseline at plan time: **247 tests /
> 509 assertions**, cs 0, analyse 0, architecture 0 violations, MSI ≈ 99.7%.
> No user-visible derivation-output change is intended; golden files under
> `tests/_expected/` must stay byte-identical unless a step explicitly says
> otherwise. API surface changes are additions only (new enum/constants/helpers);
> no public signature is removed or re-typed.
>
> **Status: Planned — nothing below has been implemented yet.** Update the
> implementation-status table as steps land (see §Implementation status).

## Severity legend (mirrors `docs/REVIEW.md` §11)

- **Medium** — fix before tagging a release (F-1, F-2).
- **Low** — quality debt, one afternoon total (F-3 … F-13).
- **Info** — documented decision / packaging nicety (F-14, F-15).

## Finding → plan-step map

| Finding | Severity | Plan step | Theme |
|---------|----------|-----------|-------|
| F-1 | Medium | R3-1 | Declare `psr/cache` (undeclared runtime dependency of `PsrCacheReflectionCache`) |
| F-2 | Medium | R3-2 | Rewrite stale `AGENTS.md` to post-Phase-12 reality |
| F-15 | Info | R3-3 | Add `license` field to `composer.json` |
| F-4 | Low | R3-4 | Centralize derived-name rules in `Naming` (`'Adapter'` / `'Interface'` suffixes, namespace join) |
| F-5 | Low | R3-5 | Hoist triplicated signature-collection loop into `AbstractDeriver` |
| F-6 | Low | R3-6 | Delete dead duplicate docblock in `FamilyDeriver::deriveReflections()` |
| F-9 | Low | R3-7 | Expose `selfFqcn()` / `parentFqcn()` on `TypeSubstitutor`; stop recomputing in `InterfaceDeriver::build()` |
| F-7 | Low | R3-8 | `'Family'` output-key constant + `Cli\Preset` backed enum (+ derive `VALUE_OPTIONS` arity) |
| F-13 | Low | R3-9 | Remove unreachable defensive branch in `DeriveCommand::validate()` |
| F-12 | Low | R3-10 | Make `RecursiveClassResolver` / `RecursiveInterfaceResolver` `final` |
| F-3 | Low | R3-11 | Mark `DeriveConfig` as a `readonly class` |
| F-8 | Low | R3-12 | Forward `$reflectionCache` / Nette `$factory` from `FamilyDeriver::deriveMember()` to member derivers |
| F-10 | Low | R3-13 | Guard `DeriveConfig::copyWith()` against unknown override keys |
| F-11 | Trivial | R3-14 | Simplify `WeakReflectionCache::get()` |
| F-14 | Info ◐ | R3-15 | Document (and optionally harden) the lexical `--base-dir` sandbox |

**Sequencing (release blockers → DRY core → CLI → polish → informational):**

```
Milestone A — Release blockers (before v0.1.0 tag):
    R3-1, R3-2, R3-3
      └─ Milestone B — DRY & naming consolidation:
            R3-4  →  R3-5  →  R3-6, R3-7
              └─ Milestone C — CLI hygiene & type safety:
                    R3-8, R3-9, R3-10
                      └─ Milestone D — Modern-PHP & robustness polish:
                            R3-11, R3-12, R3-13, R3-14
                              └─ Milestone E — Informational / security:
                                    R3-15 (decision + doc; optional code)
```

Each milestone ends with the full gate battery. Steps inside a milestone are
ordered so that later steps can build on earlier helpers (e.g. R3-5 uses nothing
from R3-4, but both touch `AbstractDeriver` consumers — land sequentially and run
the gates between them).

---

## Implementation status

| Step | Finding | Status | What shipped |
|------|---------|--------|--------------|
| R3-1 | F-1 | ⬜ Planned | — |
| R3-2 | F-2 | ⬜ Planned | — |
| R3-3 | F-15 | ⬜ Planned | — |
| R3-4 | F-4 | ⬜ Planned | — |
| R3-5 | F-5 | ⬜ Planned | — |
| R3-6 | F-6 | ⬜ Planned | — |
| R3-7 | F-9 | ⬜ Planned | — |
| R3-8 | F-7 | ⬜ Planned | — |
| R3-9 | F-13 | ⬜ Planned | — |
| R3-10 | F-12 | ⬜ Planned | — |
| R3-11 | F-3 | ⬜ Planned | — |
| R3-12 | F-8 | ⬜ Planned | — |
| R3-13 | F-10 | ⬜ Planned | — |
| R3-14 | F-11 | ⬜ Planned | — |
| R3-15 | F-14 | ⬜ Planned | — |

---

# Milestone A — Release blockers (F-1, F-2, F-15)

> Estimated effort: ~0.5 day. Exit criteria: production install is complete and
> loadable, contributor docs match reality, `composer validate` is warning-free.

## R3-1 — Declare `psr/cache` (F-1, Medium)

**Location:** `composer.json` (`require` block); `src/Reflection/PsrCacheReflectionCache.php:8`.

**Why:** `PsrCacheReflectionCache` imports `Psr\Cache\CacheItemPoolInterface`, but
`psr/cache` is absent from `composer.json`. A production install resolves only
`nette/php-generator` + `nette/utils` (verified via `composer show --no-dev`);
using the advertised PSR-6 cache fatals there unless a dev-chain package happens
to pull `psr/cache` transitively. This is exactly the class of defect Phase 0.4
fixed for Codeception modules.

**Goal:** the PSR-6 cache works in a clean `--no-dev` install of the package.

### Steps
1. Add to `composer.json` require (interface-only package; pulls no implementation):
   ```json
   "psr/cache": "^1.0 || ^2.0 || ^3.0"
   ```
2. Run `composer update psr/cache --with-all-dependencies` and commit the lock file.
3. Extend the class docblock of `PsrCacheReflectionCache` to state that the pool
   is *optional infrastructure*: the adapter works with any PSR-6 implementation
   the consumer provides (the package never requires an implementation itself).

### Acceptance
- `composer why psr/cache` reports the direct `it-pack/derive-it` link.
- In a scratch dir: `composer require it-pack/derive-it` (path-repository) +
  a script that instantiates `PsrCacheReflectionCache` against `symfony/cache`
  (dev-only in the scratch project) runs without autoload errors.
- All five gates green; no source change beyond the docblock.

## R3-2 — Refresh `AGENTS.md` (F-2, Medium)

**Location:** `AGENTS.md` (repo root).

**Why:** the agent/contributor guide describes the Phase-1-era tree: it claims
Phases 3–5 are "not yet built", counts "78 passing tests" (now 247), points to
root-level `REVIEW.md` / `IMPLEMENTATION_PLAN.md` / `ROADMAP.md` that now live in
`docs/archive/` and `docs/`, and predates the CLI, factory, caches, presets, and
Phase 9–15 hardening. Any contributor or AI agent following it will mis-navigate.

**Goal:** a guide that is true of the current tree, or a thin router into the
authoritative docs.

### Steps
1. Replace the status narrative (§1 "What this package is", §2 command/status
   table, §3 layout) with current facts:
   - Phases 0–12 complete, release-ready (cite `docs/ROADMAP.md` header).
   - Layout listing matching the real `src/` tree: `Cli/`, `Contract/{Cli,Generator,
     Reflection,Resolver}/`, `Exception/`, `Generator/` (incl. presets,
     `LeakageAuditor`, `TypeSubstitutor`, value objects), `Reflection/` (three
     cache implementations), `Entry` facade + `DeriveItFactory`.
   - Gate table with live numbers (247 tests / 509 assertions; MSI baseline).
2. Repoint every cross-reference:
   - `REVIEW.md` → `docs/REVIEW.md` (current audit) and `docs/archive/REVIEW_*.md`.
   - `ROADMAP.md` / `IMPLEMENTATION_PLAN.md` → `docs/ROADMAP.md`,
     `docs/archive/IMPLEMENTATION_PLAN.md`.
   - "How generation works" → `docs/guide/architecture.md` + the four ADRs under
     `docs/architecture/decisions/`.
3. Keep the still-valid guidance (leakage invariant, Nette-factory typing rule,
   resolver injection rule, "never break CI") verbatim where accurate.
4. Add one line noting this file must be updated when phases/docs move — the
   drift it accumulated is itself the lesson (cross-link R3-2 ↔ future reviews).

### Acceptance
- Every path referenced in `AGENTS.md` exists (`grep -oE '`[A-Za-z0-9_./-]+`'`
  spot-check).
- Test count / phase status match `composer test:unit` output and
  `docs/ROADMAP.md`.

## R3-3 — Declare a license (F-15, Info)

**Location:** `composer.json`.

**Why:** `composer validate` warns "No license specified"; blocks clean Packagist
publication.

### Steps
1. Choose per project policy (e.g. `"MIT"` or `"proprietary"` for closed source).
2. Add `"license": "<choice>"` to `composer.json`; if an open license, add the
   corresponding `LICENSE` file and a README badge/section.

### Acceptance
- `composer validate --no-check-publish` emits no warnings.
- Gates untouched (metadata-only change).

---

# Milestone B — DRY & naming consolidation (F-4, F-5, F-6, F-9)

> Estimated effort: ~1 day. Exit criteria: zero behavioral diff in golden files;
> the three signature-collection sites collapse to one; name rules exist only in
> `Naming`.

## R3-4 — Centralize derived-name rules in `Naming` (F-4, Low)

**Locations:**
- `src/Generator/AdapterGenerator.php:51` — `$this->adaptee->getShortName() . 'Adapter'`
- `src/DeriveIt.php:205–206` — same suffix plus inline namespace join (twice)
- `src/Generator/PerTraitInterfaceDeriver.php:59` — `$trait->getShortName() . 'Interface'`
- `src/Generator/Naming.php` — owns `derivedInterfaceShortName/Fqcn`, `shortName`

**Why:** the derived-name grammar (suffixes, namespace joining) is the package's
naming contract; four sites re-implement fragments of it, so a suffix or join-rule
change needs edits in four places and can silently diverge.

**Goal:** every derived name is produced by a `Naming` method; suffix literals
exist once.

### Steps
1. In `Naming`, add:
   ```php
   public const INTERFACE_SUFFIX = 'Interface';
   public const ADAPTER_SUFFIX   = 'Adapter';

   /** Unconditional suffix append — preserves current per-trait behavior. */
   public static function interfaceShortName(string $shortName): string;

   public static function adapterShortName(ReflectionClass $adaptee): string;

   /** "$ns\Short" or "Short" when $namespace === ''. */
   public static function join(string $namespace, string $shortName): string;
   ```
2. Re-implement `derivedInterfaceShortName()` / `derivedInterfaceFqcn()` on top of
   these primitives (behavior unchanged, including the `'Derived'` collision case).
3. Replace call sites:
   - `AdapterGenerator::generate()` → `Naming::adapterShortName($this->adaptee)`;
     its private `interfaceFqcn()` becomes `Naming::join($ns, $this->interface->getName())`.
   - `DeriveIt::deriveFamilyAdapter()` → `Naming::join(...)` ×2 +
     `Naming::adapterShortName(...)`.
   - `PerTraitInterfaceDeriver::deriveTraitInterfaces()` → `Naming::interfaceShortName($trait->getShortName())`.
4. **Behavior guard (do not skip):** deliberately use the *unconditional* append
   for traits, NOT `derivedInterfaceShortName()` — a trait literally named
   e.g. `LogInterface` must keep producing `LogInterfaceInterface`, not
   `LogInterfaceDerived`. Record this asymmetry as a comment on
   `interfaceShortName()`.

### Acceptance
- Full snapshot suite passes with zero `_expected/` diffs (`git diff tests/_expected` empty).
- `grep -rn "'Adapter'\|'Interface'" src/` matches only `Naming.php` constants
  (plus unrelated strings in comments/help text).
- Gates green.

## R3-5 — Extract shared signature collection into `AbstractDeriver` (F-5, Low)

**Locations:**
- `src/Generator/InterfaceDeriver.php:146–157` (`build()` inner loop)
- `src/Generator/PerTraitInterfaceDeriver.php:72–83` (`deriveTraitInterfaces`)
- `src/Generator/PerTraitInterfaceDeriver.php:131–141` (`derive` main-method loop)

**Why:** the sequence *extract → skip-check → rename-if-mapped → build signature*
is copied three times; the copies have already drifted in comments and risk
drifting in behavior (e.g. one site forgetting a future rename/rename-collision
rule).

**Goal:** one protected helper; mode-specific logic stays at the call sites.

### Steps
1. Add to `AbstractDeriver`:
   ```php
   /**
    * Builds the derived signature for one reflected method: applies skip/allow
    * filtering, the substitutor, and any configured rename. Returns null when
    * the method is filtered out.
    */
   protected function signatureFor(
       ReflectionMethod $method,
       TypeSubstitutor $substitutor,
   ): ?Method {
       if ($this->shouldSkipMethod($method->getName())) {
           return null;
       }
       $signature = $this->signatureFactory->fromMethod($method, $substitutor);
       $renameMap = $this->config->methodRenames;
       if (isset($renameMap[$method->getName()])) {
           $signature = $signature->cloneWithName($renameMap[$method->getName()]);
       }

       return $signature;
   }
   ```
2. Rewire the three loops to `?? continue` on `null`; keep site-specific concerns
   where they belong (per-trait `$claimed` ownership check stays in
   `deriveTraitInterfaces`; the flat-mode loop keeps its plain iteration).
3. Do **not** merge the three loops into one generic pipeline — the surrounding
   bookkeeping differs legitimately (claimed-set, main-vs-trait split).

### Acceptance
- Snapshot + filtering + per-trait + mutation suites pass unchanged
  (`MutationCoverageTest` is the regression net for dropped branches).
- `grep -c cloneWithName src/` drops from 3 to 1.
- Gates green.

## R3-6 — Remove dead duplicate docblock (F-6, Low)

**Location:** `src/Generator/FamilyDeriver.php:147–150` — an orphaned
`@param $map / @param $namespacePrefixMap` block stacked above the real docblock
of `deriveReflections()`.

**Why:** leftover from a refactor; PHPStan reads the first block, humans read the
second, and the first is wrong (it documents parameters the method doesn't take).

### Steps
1. Delete the orphaned block; keep the full docblock starting
   *"Core family derivation over already-built ReflectionClass instances…"*.

### Acceptance
- `vendor/bin/phpstan analyse` still 0 errors (proves the surviving block was the
  authoritative one); gates green.

## R3-7 — Report substitutions from `TypeSubstitutor` accessors (F-9, Low)

**Locations:**
- `src/Generator/InterfaceDeriver.php:117–121` (local `$selfFqcn` / `$parentFqcn` recomputation)
- `src/Generator/AbstractDeriver.php:105–114` (`createTypeSubstitutor()` computes the same values)
- `src/Generator/TypeSubstitutor.php:33–39` (holds them privately)

**Why:** `InterfaceDeriver::build()` duplicates the seeding arithmetic that
`createTypeSubstitutor()` performs, purely to report applied substitutions. Two
copies of one rule invite divergence (one gaining a config flag, the other not).

**Goal:** the substitutor is the single owner of its own inputs; reporting reads
from it.

### Steps
1. Add to `TypeSubstitutor`:
   ```php
   public function selfFqcn(): ?string;   // null when no self/static substitution configured
   public function parentFqcn(): ?string; // null when absent or not configured
   ```
2. In `InterfaceDeriver::build()`, drop the local computation; after deriving
   `$meta`, populate the reported map from `$substitutor->selfFqcn()` /
   `->parentFqcn()` instead of the locals.
3. Ensure `BuildMeta::$substitutions` content is identical before/after
   (`Phase9FeaturesTest` / `TypeSubstitutionTest` assert the reported map).

### Acceptance
- Substitution-reporting tests pass unchanged; `grep -n "selfFqcn =" src/Generator/InterfaceDeriver.php` finds nothing.
- Gates green.

---

# Milestone C — CLI hygiene & type safety (F-7, F-13, F-12)

> Estimated effort: ~0.5 day. Exit criteria: no bare preset/mode-key literals in
> the CLI; dead validation branch gone; resolver consistency.

## R3-8 — Named family-adapter output key + `Cli\Preset` enum (F-7, Low)

**Locations:**
- `src/Cli/DeriveCommand.php:274` — `['Family' => ...]` magic output key
- `src/Cli/ArgParser.php` — preset literals `'strict'|'adapter'|'family'` in the
  `match` (:158–162), error message (:163), and help text; `VALUE_OPTIONS`
  constant (:36–41) duplicating the handler-table keys

**Why:** the preset vocabulary and the family-adapter output key are CLI contracts
that currently exist as repeated bare strings; a typo in any copy fails at
runtime, and adding a preset means touching three places plus the help text.

**Goal:** one typed home per vocabulary, mirroring what `DeriveMode` did for modes.

### Steps
1. New `src/Cli/Preset.php`:
   ```php
   enum Preset: string
   {
       case STRICT  = 'strict';
       case ADAPTER = 'adapter';
       case FAMILY  = 'family';

       /** @return list<string> comma-free list of valid values, for messages/help. */
       public static function values(): array;

       public function toConfig(): DeriveConfig; // strict()/forAdapter()/forFamily()
   }
   ```
2. `ArgParser`: parse via `Preset::tryFrom()`; derive the error-message value list
   from `Preset::values()`; `buildConfig()` starts from `$preset?->toConfig() ??
   new DeriveConfig()` — the `match` disappears.
3. Sync `writeHelp()` to enumerate `Preset::values()` (keep the hand-written help
   format; only the value list must stay in sync).
4. `DeriveCommand`: add `private const FAMILY_ADAPTER_SOURCE_KEY = 'Family';` and
   use it at :274 (and wherever the key is consumed downstream).
5. Optional (same theme, small): replace the `VALUE_OPTIONS` list with an
   `optionArity(): array<string, int>` map co-located with the handler table so a
   new value-consuming flag cannot forget the second registration. If taken,
   `parse()` consults arity instead of `in_array(..., self::VALUE_OPTIONS)`.

### Acceptance
- `ArgParserTest` gains: invalid-preset message enumerates all three values;
  each valid preset produces its expected `DeriveConfig` flags.
- Existing CLI tests pass; help-text snapshot (if any) updated.
- `grep -rn "'strict'\|'family'" src/Cli/` matches only the enum file.
- Gates green.

## R3-9 — Drop the unreachable validation branch (F-13, Low)

**Location:** `src/Cli/DeriveCommand.php:135` — inside the FLAT/PER_TRAIT/ADAPTER
arm of `validate()`, the check `$input->root !== null` can never fire because
`:126–132` already promoted every `--root` input to `FAMILY` mode (rejecting
`FAMILY_ADAPTER` first).

**Why:** unreachable guards imply behaviors that cannot occur, mislead readers,
and can mask future regressions of the promotion logic.

### Steps
1. Delete the inner `if ($input->root !== null) { throw ... }` block.
2. Preserve intent with a comment on the promotion block: "`--root` forces family
   mode; single-target validation below therefore never sees a root."
3. Confirm the deleted error string has no test asserting it (search
   `tests/Unit/Cli/`). If one exists, repurpose it: `--root` +
   `--mode=family-adapter` still throws (the *reachable* guard at :128).

### Acceptance
- `DeriveCommandTest` green; explicit new case: `--root=X --mode=flat` yields a
  family derivation (documents the promotion), not an error.
- Gates green.

## R3-10 — Finalize the two remaining resolvers (F-12, Low)

**Location:** `src/Resolver/RecursiveClassResolver.php:11`, `src/Resolver/RecursiveInterfaceResolver.php:11`
(`RecursiveTraitResolver` is already `final`).

**Why:** nothing extends them; `final` matches the package convention and lets
PHPStan/opcode caches reason locally.

### Steps
1. Add `final` to both class declarations.

### Acceptance
- Gates green (no subclass exists in tests/fixtures — verified by grep before
  editing).

---

# Milestone D — Modern-PHP & robustness polish (F-3, F-8, F-10, F-11)

> Estimated effort: ~0.5 day. Exit criteria: `DeriveConfig` is a `readonly class`;
> family composition genuinely shares its assembled collaborators; runtime-only
> failure modes guarded.

## R3-11 — `final readonly class DeriveConfig` (F-3, Low)

**Location:** `src/Generator/DeriveConfig.php:25`.

**Why:** every property is already `public readonly`; marking the class `readonly`
(PHP 8.2+, idiomatic under the active `@PHP84Migration` ruleset and consistent
with `AbstractDeriver`, the value objects, and the filters) makes immutability a
language guarantee and forbids accidental dynamic properties.

**Guardrails:** `readonly class` forbids untyped properties and extension —
`DeriveConfig` is `final` with fully typed props, and `copyWith()` reconstructs
via the constructor, so both constraints are already satisfied. Verify no code
clones/configures it dynamically (`var_export`, `unset` on props) before merging.

### Steps
1. Change the declaration to `final readonly class DeriveConfig`.
2. Run the full suite — `get_object_vars()` in `copyWith()` is unaffected by
   `readonly` (it reads, never writes).

### Acceptance
- Gates green; `infection` unaffected (no mutable path existed).

## R3-12 — Share the assembled set across family members (F-8, Low)

**Location:** `src/Generator/FamilyDeriver.php:195–198` — `deriveMember()` builds
a per-member `new InterfaceDeriver(signatureFactory: ..., config: ...)` without
forwarding its own `$reflectionCache` or Nette `$factory`.

**Why:** harmless today (reflections are passed in directly, so no re-reflection
occurs), but it quietly contradicts the composition story documented on
`DeriveItFactory` ("one cache + one Factory shared across the whole set").

### Steps
1. Forward both collaborators:
   ```php
   return new InterfaceDeriver(
       signatureFactory: new MethodSignatureFactory(factory: $this->factory, config: $memberConfig),
       config: $memberConfig,
       factory: $this->factory,
       reflectionCache: $this->reflectionCache,
   )->deriveWithSubstitutor($reflection, $substitutor, $allowedNamespaces);
   ```

### Acceptance
- Gates green; no golden diffs; `FamilyDeriverTest` memoization assertions
  unchanged (derivation remains exactly-once per config).

## R3-13 — Fail fast on unknown `copyWith()` override keys (F-10, Low)

**Location:** `src/Generator/DeriveConfig.php:143–151`.

**Why:** withers address constructor parameters by string key. Renaming a
constructor parameter silently breaks the wither only at call time, and neither
IDE nor PHPStan flags it. A cheap guard converts the latent breakage into an
immediate, obvious error during development.

### Steps
1. Inside `copyWith()`, before merging:
   ```php
   foreach ($overrides as $key => $value) {
       if (!property_exists($this, $key)) {
           throw new \LogicException(sprintf(
               'Unknown DeriveConfig override key "%s" — update the wither to match the constructor parameter.',
               $key,
           ));
       }
       $vars[$key] = $value;
   }
   ```
2. Use `LogicException` (programmer error, consistent with the exception family in
   `src/Exception/`); do not catch it anywhere.

### Acceptance
- One new `DeriveConfigTest` case: `copyWith(['nope' => 1])` throws.
- All withers still pass (they only use real keys).

## R3-14 — Simplify `WeakReflectionCache::get()` (F-11, Trivial)

**Location:** `src/Reflection/WeakReflectionCache.php:27` —
`return $ref !== null ? ($ref->get() ?? null) : null;` — the inner `?? null` is
redundant (`WeakReference::get()` is already nullable).

### Steps
1. Collapse to `return $ref?->get();`.

### Acceptance
- `WeakReflectionCacheTest` green; gates green.

---

# Milestone E — Informational / security decision (F-14)

> Estimated effort: ~1 hour (doc-only) · ~half day if hardening is chosen.

## R3-15 — Sandbox semantics: document, optionally harden (F-14, Info ◐)

**Location:** `src/Cli/DeriveCommand.php` (`assertSafeOutputPath()`,
`canonicalizePath()`); trust-model notes in `--help` and `docs/guide/usage.md`.

**Why (recorded decision):** the `--base-dir` guard is intentionally *lexical* —
`realpath()` on the base, canonicalized comparison for the target — so it works
before directories exist. Consequence: a symlink planted inside the base dir can
still point the write target outside. Under the documented trusted-operator model
this is accepted (REVIEW.md §9); the residual risk must simply be stated where
operators look.

### Steps (doc-only path — default)
1. Extend the `Security:` section of `writeHelp()` and the CLI chapter of
   `docs/guide/usage.md` with one sentence: "`--base-dir` is a lexical guard; it
   does not resolve symlinks in the target path. Point `--out` only at paths you
   control."
2. Cross-reference from `assertSafeOutputPath()`'s docblock so the constraint
   lives next to the code.

### Steps (optional hardening — only if ever needed)
3. After computing `$canonical`, if the deepest existing ancestor differs from
   `realpath($base)` containment, reject. Do **not** `realpath()` the target
   itself (breaks the pre-existence contract). Add CLI tests: symlink-in-base
   escape rejected; normal relative path still accepted.

### Acceptance
- Doc path: help text and usage doc mention lexical semantics; no behavior change;
  gates green.
- Hardening path (if chosen): new negative/positive CLI tests listed above pass.

---

## Cross-cutting verification protocol

Run after **each milestone** (not just at the end):

```bash
composer test:unit          # expect ≥ 247 tests (more as tests are added)
composer cs:check           # 0 files to fix
composer analyse            # 0 errors
composer architecture       # 0 violations
```

Once after Milestone D (the last behavior-adjacent milestone):

```bash
composer infection          # min-msi=80; expect ≈ baseline (99%+)
git diff --stat tests/_expected/   # must be empty — no golden drift anywhere in the plan
```

If any gate regresses: stop, bisect to the step (steps are deliberately small and
independent), fix forward — never loosen a gate to pass a step.

## Out of scope

- Any change to derivation output, mode semantics, or the leakage invariant.
- Ports for internal helpers (`MethodSignatureFactory`, `SourceRenderer`,
  `TypeSubstitutor`) — REVIEW_3's F-3 skip rationale stands; revisit only if
  finer-grained deriver substitution becomes a real requirement.
- Mutation-config tuning (timeout share discussed in REVIEW.md §12) — separate
  measure-first effort, tracked informally; not a finding in scope here.
- Sibling monorepo packages.

## Definition of Done

- [ ] R3-1 … R3-15 implemented (or explicitly skipped with rationale recorded in
      the status table, as PLAN_2 did for RP-3).
- [ ] All five gates green on PHP 8.4 and 8.5 (CI matrix).
- [ ] `tests/_expected/` byte-identical throughout.
- [ ] `AGENTS.md`, `--help`, and `docs/guide/usage.md` consistent with the shipped
      state (no new drift introduced by this plan).
- [ ] `docs/REVIEW.md` annotated with the resolution column/table linking each
      F-id to its R3-step, mirroring how REVIEW_3's findings were closed.
- [ ] Ready to tag `v0.1.0` (Milestones A–D complete; E may trail as doc-only).
