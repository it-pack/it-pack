# DeriveIt — Refactoring Plan (REVIEW.md F-1 … F-12)

> Companion to `docs/REVIEW.md` (2026-08-25 audit; supersedes the closed
> `docs/archive/REFACTORING_PLAN_3.md`). Prepared: 2026-08-25 · Package:
> `it-pack/derive-it` (+ sibling `it-pack/derive-it-runit`) · Scope: every
> finding in REVIEW.md §14, organized into three milestones (A–C) with
> individually verifiable steps `R4-1 … R4-9`.
>
> **Hard rule:** every step must keep all quality gates green — DeriveIt
> (`test:unit`, `cs:check`, `analyse`, `architecture`, `infection`,
> golden files byte-stable) and the bridge (`test:unit`, `analyse`,
> `cs:check`). Baseline at plan time: **269 tests / 572 assertions**, cs 0,
> analyse 0, deptrac 0, coverage **95.00%**, MSI ≈ 97.3%; bridge 4 tests /
> 21 assertions.
>
> **Directive interpretations carried over from REVIEW.md §0** (binding here):
> constructor-promoted properties satisfy property-before-constructor ordering;
> golden `*.adapter.php` files are exempt from one-type-per-file; value
> objects, Nette AST nodes, and exceptions are exempt from the
> factories-only instantiation rule; framework lifecycle methods (`setUp` /
> `_before`) are exempt from public-first ordering once documented (R4-8).
>
> **Status: Milestone A (R4-1 … R4-3) implemented and gated green; Milestone B
> (R4-4 … R4-6) implemented and gated green (cs:lines, coverage:check floor 96%,
> bridge infection MSI 100%); Milestone C (R4-7 … R4-9) implemented and gated
> green (2026-08-28). All R4 steps shipped — the plan's three milestones are
> complete.** Update the implementation-status table as steps land.

## Severity legend (mirrors REVIEW.md §14)

- **Medium** — fix soon; directive substance or gate headroom (F-1…F-4, F-12).
- **Low** — consistency/polish (F-5…F-8).
- **Info** — accepted-with-record items (F-9…F-11); no steps, rationale below.

## Finding → plan-step map

| Finding | Severity | Plan step | Theme |
|---------|----------|-----------|-------|
| F-3 | Medium | R4-1 | Telemetry vocabularies → constant-only interfaces (`Contract/Telemetry`) |
| F-4 (+F-7 core) | Medium | R4-2 | Dissolve `AbstractDeriver` → `final readonly DerivationCore` (composition) |
| F-7 (remainder) | Low | R4-3 | Instantiation-discipline polish + documented exemptions |
| F-1 | Medium | R4-4 | Wrap all >120-col lines + enforce via PHPCS `LineLength` |
| F-2 (+F-10) | Medium | R4-5 | Targeted tests for the four <95% files, numeric coverage gate, report script |
| F-12 | Medium | R4-6 | Bridge: infection + coverage + CI wiring, MSI baseline |
| F-5 | Low | R4-7 | Extract inline test types to PSR-4 support/fixture files |
| F-6 | Low | R4-8 | Declaration-order fixes + documented exemptions (promotion, lifecycle) |
| F-8 | Low ◐ | R4-9 | Optional complexity splits (`FileTargetResolver`) |
| F-9 / F-11 | Info | — | Accepted without steps (rationale in §Out of scope) |

**Sequencing (structural core → enforcement → polish):**

```
Milestone A — Directive substance:
    R4-1 (vocabularies)  ──►  R4-2 (kill abstract base, fold core plumbing)
                        ──►  R4-3 (creation-point polish + exemptions doc)
          └─ Milestone B — Enforcement & headroom:
                R4-4 (120 cols + sniffer)  ──►  R4-5 (tests + coverage gate)
                                            └─► R4-6 (bridge mutation/CI)
                    └─ Milestone C — Consistency polish:
                          R4-7 (test type extraction) ──► R4-8 (order fixes)
                                                    └─► R4-9 (optional split)
```

R4-1 lands first because R4-2 rewrites the emission sites and should reference
the new constants, not migrate strings twice. Each milestone ends with the full
gate battery on both packages.

---

## Implementation status

 | Step | Finding | Status | What shipped |
 |------|---------|--------|--------------|
 | R4-1 | F-3 | ✅ Done | `Contract/Telemetry/{DeriveEvents,DeriveMeasurements,DeriveUnits}.php` (typed const string) hold every emitted event/measurement/unit/attribute-value name; all emission sites (InterfaceDeriver, PerTraitInterfaceDeriver, FamilyDeriver, DeriveIt) and the bridge `RunItDerivationEventSink` `match` arms reference them. `DerivationTelemetryTest` kinds assertions reference the constants; AGENTS.md telemetry convention added. |
 | R4-2 | F-4, F-7 | ✅ Done | `AbstractDeriver` deleted; new `final readonly Generator\DerivationCore` owns reflection memoization, skip/allow filtering, substitutor seeding (`self`/`static`/`parent` + explicit map), the signature pipeline, plus `sourceRenderer()`/`eventEmitter()`/`config()` accessors. InterfaceDeriver + PerTraitInterfaceDeriver compose a `DerivationCore` (via `DerivationCore::create()`); FamilyDeriver still assembles members through the composite builder. |
 | R4-3 | F-7 | ✅ Done | `DeriveItFactory::sourceRenderer()` + `eventEmitter()` accessors; facade `deriveFamily*` render loops and `diff()` route through them; `DeriveCommand` ctor accepts optional injectable `ArgParser` + verbose-writer closure (DI seams); `FamilyDeriver::deriveMember()` documented as the composite builder; AGENTS.md instantiation-discipline line added. |
 | R4-4 | F-1 | ✅ Done | `phpcs.xml.dist` (`Generic.Files.LineLength` 120) added (no `<file>` tags — pass paths on CLI); all 76 >120-col lines wrapped in `src` (54) + `tests` (22) via bottom-up multi-line edits; `composer cs:lines` script (`vendor/bin/phpcs --standard=phpcs.xml.dist src tests`) added; AGENTS.md §2 updated. |
 | R4-5 | F-2, F-10 | ✅ Done | `scripts/coverage_report.php` (Clover parse + per-file/overall, default floor 96) added; `composer coverage:check` script (`codecept run Unit --coverage-xml --coverage-html && php scripts/coverage_report.php --min=96`) added; targeted tests drove overall 94.05% → 97.59% and the four named files (DeriveCommand 95.14%, ConstructorFilter 100%, LeakageAuditor 98.08%, Naming 95.24%) to ≥95%. |
 | R4-6 | F-12 | ✅ Done | Bridge `it-pack/derive-it-runit` gained `infection/infection`, `infection.json5` (testFramework phpunit, min-msi 80), `composer infection`/`coverage`/`cs:lines` scripts, its own `phpcs.xml.dist`, and `.github/workflows/ci.yml`; a direct branch-coverage test (`DerivationExecutionFunctionalTest::testSinkExercisesEveryRecorderBranch`) pins every recorder arm, recorded MSI 100% on PHP 8.4/8.5. |
 | R4-7 | F-5 | ✅ Done | Test types extracted to `tests/Support/Fixture/` (PSR-4 `Tests\Support\Fixture\`): `ArgParserSample`; `CliSampleTarget` + `CliSampleTrait` + `CliSampleTraitUser` + `CliSampleA` + `CliSampleB`; `RecordingDeriveEventSink` (shared by DeriveEventEmitterTest + DerivationTelemetryTest); `CountingReflectionCache` + `InMemoryPool` + `CacheItem`; `NamingEndsWithInterface` + `NamingCollideInterface` + `NamingCollide`; `LeakForeignReturn` + `LeakBuiltinReturn` + `LeakOwnedReturn` (plus `Foreign\Ns\Thing`, autoloaded via a new `Foreign\` dev PSR-4 root → `tests/Support/Fixture/foreign/`). Bridge `ReportWidget` extracted to `DeriveItRunit/tests/Fixture/` (`ItPack\DeriveItRunit\Tests\Fixture\`). The original audit undercounted (5 files); the true hand-written multi-type set was 7 — all extracted, leaving the tree `MULTI = 0` (goldens excluded). AGENTS.md §4 carries the one-type-per-file ledger + golden exemption. |
 | R4-8 | F-6 | ✅ Done | `ArgParser::arities()` relocated below `parse()`; `DeriveIt::factory()` relocated beneath the public static API block. AGENTS.md §4 carries the declaration-order rule + the two standing exemptions (constructor-promoted properties, lifecycle methods). |
 | R4-9 | F-8 | ✅ Done ◐ | Tokenizer scan extracted from `DeriveCommand::classFromFile()` into `src/Cli/FileTargetResolver.php` (`final readonly`, injected as an optional DI seam defaulting to a new instance; `resolveType()` delegates). New `FileTargetResolverTest` covers namespace scan, global-class scan, and the no-declaration error. `DeriveCommand` aggregate CC drops; behavior identical (same error strings asserted by DeriveCommandTest). |

---

# Milestone A — Directive substance (F-3, F-4, F-7)

> Estimated effort: ~1.5 days. Exit criteria: zero telemetry string literals
> outside the vocabulary interfaces; zero abstract classes; both derivers
> `final readonly` with unchanged public APIs; all gates green; goldens
> byte-stable.

## R4-1 — Vocabulary interfaces for telemetry facts (F-3, Medium)

**Locations:** emission sites in `src/Generator/InterfaceDeriver.php` (12),
`PerTraitInterfaceDeriver.php` (18), `FamilyDeriver.php` (3),
`src/DeriveIt.php` (3); unit strings duplicated in
`../DeriveItRunit/src/RunItDerivationEventSink.php`.

**Why:** activity names, occurrence types, measurement names, and units are the
package's cross-boundary contract with sinks — currently bare strings repeated
at ~36 sites. A typo in one site silently breaks dashboards/tests while
goldens stay green. The brief requires such vocabularies to live in
interfaces.

**Goal:** single named home per vocabulary; every emission site and test
references constants.

### Steps
1. Add three constant-only interfaces in `src/Contract/Telemetry/`
   (interfaces may not carry runtime behavior — constants only, per directive):
   ```php
   interface DeriveEvents          // activities + occurrences
   {
       public const EVENT_DERIVATION_STARTED   = 'derivation.started';
       public const EVENT_DERIVATION_COMPLETED = 'derivation.completed';
       public const ACTIVITY_GRAPH_WALK        = 'graph.walk';
       public const ACTIVITY_SURFACE_EXTRACT   = 'surface.extract';
       public const ACTIVITY_LEAKAGE_AUDIT     = 'leakage.audit';
       public const ACTIVITY_DIFF_COMPUTE      = 'diff.compute';
       public const OCCURRENCE_DRIFT_DETECTED  = 'drift.detected';
       public const OCCURRENCE_DRIFT_CLEAN     = 'drift.clean';
   }

   interface DeriveMeasurements
   {
       public const MEASUREMENT_FAMILY_MEMBERS      = 'family.members';
       public const MEASUREMENT_METHODS_DERIVED     = 'methods.derived';
       public const MEASUREMENT_METHODS_DROPPED     = 'methods.dropped';
       public const MEASUREMENT_SUBSTITUTIONS       = 'substitutions.applied';
       public const MEASUREMENT_CONSTANTS_LIFTED    = 'constants.lifted';
       public const MEASUREMENT_SOURCE_BYTES        = 'source.bytes';
       public const SURFACE_TRAIT_INTERFACES        = 'trait-interfaces';
       public const SURFACE_MAIN                    = 'main';
       public const GRAPH_CLASSES                   = 'classes';
       public const GRAPH_TRAITS                    = 'traits';
   }

   interface DeriveUnits
   {
       public const UNIT_COUNT = 'count';
       public const UNIT_BYTES = 'By';
   }
   ```
2. Replace every literal at the emission sites with the corresponding constant
   (emitter call sites only — `DeriveEventEmitter` itself takes strings).
3. Update `DerivationTelemetryTest` / `DeriveConsoleSinkTest` expectations to
   reference the constants (assertions stay string-equal).
4. Bridge: switch `RunItDerivationEventSink::measured()`'s `match` arms and the
   functional-test expectations to `DeriveUnits::*`. Its own occurrence-type
   builder keeps its `FACT_PREFIX`/version constants (already named).
5. Document the three interfaces in AGENTS.md's telemetry convention entry
   ("new event/measurement/unit ⇒ add a constant there, then emit").

### Acceptance
- `grep -rn "'surface\.extract'\|'family\.members'\|'By'" src ../DeriveItRunit/src`
  matches only the interface files (and the sink's unit mapping comments).
- All gates green in both packages; goldens byte-stable; no behavior change
  provable by the identical-string assertions.

## R4-2 — Dissolve `AbstractDeriver` into composed `DerivationCore` (F-4 + F-7 core, Medium)

**Location:** `src/Generator/AbstractDeriver.php` (sole abstract class,
REVIEW.md §3); consumers `InterfaceDeriver`, `PerTraitInterfaceDeriver`;
construction sites listed under F-7.

**Why:** the brief forbids abstract classes in favor of traits/composition.
Composition is preferred (REVIEW.md §3): it removes inheritance, makes the
shared pipeline independently instantiable/mutable-testable, and gives the
creation-discipline refactor (F-7) a natural home.

**Goal:** no abstract classes anywhere; both derivers remain `final readonly`,
publicly identical, backed by `DeriverInterface`.

### Steps
1. Create `src/Generator/DerivationCore.php`:
   ```php
   final readonly class DerivationCore
   {
       public function __construct(
           private DeriveConfig $config,
           private ReflectionCacheInterface $reflectionCache,
           private ?Factory $factory,
           private DeriveEventEmitter $eventEmitter,
       ) {}

       public static function create(DeriveConfig $config, ReflectionCacheInterface $cache, ?Factory $factory, ?DeriveEventSinkInterface $sink): self;

       public function events(): DeriveEventEmitter;
       public function reflect(ReflectionClass|string $class): ReflectionClass;
       public function shouldSkipMethod(string $name): bool;
       public function seedTypeMap(ReflectionClass $reflection): array;
       public function createTypeSubstitutor(ReflectionClass $reflection): TypeSubstitutor;
       public function signatureFor(ReflectionMethod $method, TypeSubstitutor $substitutor): ?Method;
   }
   ```
   Bodies moved verbatim from `AbstractDeriver` (they read only `$this->config/
   reflectionCache/signatureFactory/events`). The signature factory becomes a
   core-owned collaborator built in `create()` — removing the derivers'
   internal `new MethodSignatureFactory` (F-7 item).
2. Rewrite both derivers:
   - delete `extends AbstractDeriver`;
   - hold `private readonly DerivationCore $core;` constructed in their
     existing constructors (same parameter lists — public API unchanged);
   - rewire `$this->events->…` → `$this->core->events()->…`,
     `$this->shouldSkipMethod(...)` → `$this->core->shouldSkipMethod(...)`,
     etc.; `signatureFor` moves into core so `cloneWithName` stays single-site.
3. Delete `AbstractDeriver.php`.
4. **Guardrail:** before deleting, `grep -rn "AbstractDeriver\|parent::__construct" src tests`
   — nothing may subclass or invoke parent plumbing (verified true today).
5. Update `docs/guide/architecture.md` deriver section (composition note) and
   REVIEW.md's F-4 pointer via the status table.

### Acceptance
- `grep -rn "abstract " src ../DeriveItRunit/src --include='*.php' | grep -v '\*'` → empty.
- Public API surface unchanged (existing suites pass untouched);
  `MutationCoverageTest` still kills the moved branches (mutation score does
  not drop more than noise).
- Gates green; goldens byte-stable.

## R4-3 — Creation-point polish + documented exemptions (F-7 remainder, Low)

**Locations:** `DeriveIt` facade (`SourceRenderer` ×2, `DeriveEventEmitter`),
`DeriveCommand::execute()` (`ArgParser`, `DeriveConsoleSink`),
`FamilyDeriver::deriveMember()` (member deriver assembly),
`MethodExtractor::resolveFilters()` (default filter chain).

**Why:** REVIEW.md §7 grades these as the remaining service-like constructions
outside factories/builders.

**Goal:** every service construction either sits in a factory/builder or is a
documented exemption.

### Steps
1. Add thin accessors to `DeriveItFactory`: `sourceRenderer(): SourceRenderer`
   and `eventEmitter(): DeriveEventEmitter` (both wrap the shared sink);
   facade `deriveFamily*` rendering loops and `diff()` use them.
2. `DeriveCommand`: constructor gains optional `?ArgParser $parser = null` and
   `?Closure(DeriveEventSinkInterface): void`-style console-sink writer —
   defaults preserve current behavior; bin/derive unchanged. *(Scope guard:
   inject the writer closure, not the sink, so verbose formatting stays owned
   by the Cli layer.)*
3. `FamilyDeriver::deriveMember()`: keep as-is, but document in the method
   docblock + AGENTS.md that FamilyDeriver is an intentional **composite
   builder** (Generator cannot reach the Entry-layer factory without breaking
   layering — deptrac direction).
4. `MethodExtractor`: keep the internal default chain; document the ctor
   variadic as the injection seam (explicit chains are already supported).
   Record as resolved-by-documentation.
5. AGENTS.md conventions gain one line: "Instances: services come from
   factories/composite builders; value objects, AST nodes, and exceptions are
   constructed at point of use."

### Acceptance
- `grep -n "new SourceRenderer\|new DeriveEventEmitter" src/DeriveIt.php` → 0.
- No behavior change; gates green both packages; exemptions visible in
  AGENTS.md and the two touched docblocks.

---

# Milestone B — Enforcement & headroom (F-1, F-2, F-12)

> Estimated effort: ~1 day. Exit criteria: line-limit enforced by tooling;
> coverage ≥ 96% with a numeric CI gate; bridge mutated with recorded MSI.

## R4-4 — Enforce the 120-column limit (F-1, Medium)

**Status:** ✅ Done (2026-08-25).

**Locations:** 58 executable + 3 docblock lines over 120 cols (inventory in
REVIEW.md §6; worst: `DeriveIt.php` docblocks 168–174, `DeriveItFactory.php`
145–162, `ArgParser.php:103`, `DeriveCommand.php:151`). php-cs-fixer has no
line-length fixer, so enforcement needs PHPCS.

### Steps
1. Mechanical wrap of all flagged lines (one-per-line parameters/arguments
   where readability benefits, per the brief). Docblocks re-flowed manually.
2. Add `squizlabs/php_codesniffer` to `require-dev`; create `phpcs.xml.dist`:
   `Generic.Files.LineLength` with `lineLimit=120`, `absoluteLineLimit=120`
   (hard error), scanning `src` (+ `tests` at the same limit — the 23 test
   offenders get wrapped in the same pass).
3. Composer script `cs:lines`: `vendor/bin/phpcs --standard=phpcs.xml.dist`;
   append to CI after `cs:check`. Mirror for the bridge package (7 lines there).
4. Re-run full battery — wrapping must be whitespace-only.

### Acceptance
- `composer cs:lines` exits 0 in both packages.
- `git diff --word-diff` shows no token changes outside whitespace/newlines;
  gates green; goldens byte-stable.

## R4-5 — Close the coverage gaps + numeric gate (F-2 + F-10, Medium)

**Status:** ✅ Done (2026-08-25).

**Locations:** `DeriveCommand::classFromFile()` (0%), `ConstructorFilter`
(88.89%), `LeakageAuditor` (91.07%), `Naming` (93.10%); composer `coverage`
script; CI workflow.

**Why:** total line coverage is exactly 95.00% — the floor holds by luck, not
by margin; the CLI's file-target path is completely untested even though it is
a documented feature; no numeric gate exists.

**Goal:** ≥ 96% total with the four sub-95% files above 95%; the percentage is
machine-enforced in CI; per-test attribution path documented.

### Steps
1. New CLI test: `derive <fixtures-path>/SomeClass.php --out=<tmpfile>` —
   exercises `classFromFile()` end-to-end (tokenizer scan + file write).
   Assert written source contains the derived interface.
2. Micro-tests: `dropDestruct=false` keeps `__destruct` (ConstructorFilter);
   deep-audit edge arms for `LeakageAuditor` (global-class rejection, allowed-
   namespace prefix match); `Naming::derivedInterfaceShortName()` on a name
   already ending in `Interface` (Derived-marker branch).
3. Promote the audit helpers into `scripts/coverage_report.php`: prints total %
   + per-file table from `tests/_output/coverage.xml`; exits non-zero below a
   configurable floor. Composer script `coverage:check` runs it; CI adds the
   step after `composer coverage` with floor **96**.
4. Record the leakage methodology (REVIEW.md §13) next to the script: per-test
   attribution remains unavailable under Codeception; Infection kill-attribution
   logs are the standing proxy — revisit if Codeception emits test-scoped
   coverage.

### Acceptance
- `composer coverage:check` green at floor 96; the four files individually
  ≥ 95% (verify via the script's table).
- New tests fail meaningfully when their target branches are mutated
  (spot-check via one manual mutant or rely on next full infection run).
- Gates green; goldens untouched.

## R4-6 — Bridge mutation testing + CI (F-12, Medium)

**Status:** ✅ Done (2026-08-25).

**Location:** `../DeriveItRunit` (composer.json, new `infection.json5`,
new `.github/workflows/ci.yml` or monorepo-equivalent).

**Why:** REVIEW.md F-12 — the bridge ships tests but no mutation lever, no
coverage measurement, no CI.

### Steps
1. `composer require --dev infection/codeception-adapter`-equivalent for
   PHPUnit standalone: configure Infection with `testFramework: phpunit`,
   source `src`, `min-msi: 80`, timeout 20.
2. Add `infection.json5`, composer script `infection`, plus `coverage` script
   (`phpunit --coverage-xml`) reusing R4-5's checker pattern (floor 90 — small
   package, high bar).
3. Add a CI workflow mirroring DeriveIt's (validate, cs, analyse, infection)
   on PHP 8.4/8.5.
4. Run once; record the baseline MSI + escaped-mutant profile in this file's
   status table (expected: mapping `match` arms and prefix concatenation are
   the interesting mutants).

### Acceptance
- `composer infection` green in the bridge; MSI baseline recorded here.
- DeriveIt's suite unaffected (path-repo installs are isolated per vendor dir).

---

# Milestone C — Consistency polish (F-5, F-6, F-8)

> Estimated effort: ~0.5 day. Exit criteria: PSR-4 clean hand-written tree;
> ordering rule satisfied or explicitly exempted in AGENTS.md.

## R4-7 — Extract inline test types (F-5, Low)

**Locations:** `ArgParserTest` (`ArgParserSample`), `DeriveCommandTest`
(`CliSampleTarget/Trait/TraitUser/A/B`), `DerivationTelemetryTest`
(`RecordingDeriveEventSink`), `Phase15BuildMetaAndCacheTest`
(`CountingReflectionCache`, `InMemoryPool`, `CacheItem`), bridge
`DerivationExecutionFunctionalTest` (`ReportWidget`).

**Why:** one-type-per-file applies to all hand-written code; five test files
currently define two-plus types.

### Steps
1. Create `tests/Support/Fixture/` (autoload-dev `Tests\Support\Fixture\`)
   and `../DeriveItRunit/tests/Fixture/` respectively; move each fixture under
   its final name (e.g. `CliSampleTarget.php`, `RecordingDeriveEventSink.php`
   under a `Support/` namespace).
2. Update `use` statements; delete inline declarations.
3. Golden outputs stay exempt — add one sentence to AGENTS.md §layout noting
   the exemption.

### Acceptance
- Token audit reports MULTI = 0 across hand-written trees (goldens excluded).
- Suites green; no behavioral edits inside moved classes.

## R4-8 — Declaration-order fixes + exemption ledger (F-6, Low)

**Locations:** `ArgParser::arities()` (private static before public `parse()`);
`DeriveIt::factory()` (private static above the public static API);
AGENTS.md conventions section.

### Steps
1. Move `arities()` below the public methods (it is a lookup helper for
   `parse()`; pure relocation).
2. Relocate `DeriveIt::factory()` beneath the public static block — or, if
   kept adjacent to the API for readability, record it as a documented
   exemption (choose one; the ledger is what matters).
3. Extend AGENTS.md conventions with the ordering rule + the two standing
   exemptions: constructor-promoted properties (language-inseparable) and
   framework lifecycle methods preceding tests.

### Acceptance
- Re-run the token-order audit: production ORDER violations = 0 (modulo the
  documented exemptions); tests show only lifecycle-pattern entries.
- Gates green.

## R4-9 — Optional complexity split: `FileTargetResolver` (F-8, Low ◐)

**Location:** `DeriveCommand::classFromFile()` + `resolveType()`.

**Why/Goal:** REVIEW.md §9 names `DeriveCommand` (CC 95 aggregate) as the one
split candidate worth doing — and only because R4-5 adds its first real test,
making extraction safe. Extract tokenizer scanning into
`src/Cli/FileTargetResolver.php` (`final readonly`, returns FQCN or throws),
constructor-injected into `DeriveCommand` with the current implementation as
default.

### Steps
1. Extract + inject; `resolveType()` delegates.
2. Move R4-5's file-target test assertions to also cover the resolver directly
   (unit-level: namespace scan, global class, no-declaration error).
3. Skip if R4-5 lands and CC feels manageable — the finding is Low ◐; record
   the decision either way.

### Acceptance
- Gates green; `DeriveCommand` aggregate CC drops measurably; behavior
  identical (same error strings asserted by existing tests).

---

## Cross-cutting verification protocol

After **each milestone**, both packages:

```bash
composer test:unit && composer cs:check && composer analyse   # + architecture (DeriveIt)
git diff --stat tests/_expected/                              # must stay empty
```

Once after Milestone A (behavior-adjacent core refactor) and again at the very
end:

```bash
composer infection          # min-msi=80; expect ≈ baseline (97.3%) post-R4-2
composer coverage:check     # after R4-5 exists (floor 96)
```

Regression policy: stop at the failing step, fix forward, never loosen a gate
or rewrite a golden to pass.

## Out of scope / accepted without steps

- **F-9 (mutation noise)** — 36 escaped mutants are string-literal/timing flips
  in pre-existing formatting code; profile stable across three runs. Accepted;
  revisit only if escapes grow beyond ~50 or leave the known files.
- **F-10 partial** — per-test coverage attribution is a tooling limitation
  (Codeception does not emit test-scoped coverage); the Infection
  kill-attribution proxy stands until that changes. R4-5 ships the reusable
  report script so a future tooling upgrade plugs in.
- **F-11** — internal concretes (`DeriveEventEmitter`, `ParseState`, helpers)
  intentionally portless; revisiting only on demonstrated need.
- Ports for `TypeSubstitutor`/`SourceRenderer` remain skipped per the standing
  REVIEW_3-era rationale.
- Sibling packages other than `DeriveItRunit`.

## Definition of Done

- [x] R4-1 … R4-9 implemented (R4-9 shipped as the optional `FileTargetResolver`
      extraction rather than declined — rationale in the status table).
- [x] Zero abstract classes; zero telemetry literals outside vocabulary
      interfaces; `cs:lines` + `coverage:check` green and wired into CI.
- [x] All five DeriveIt gates green on PHP 8.4/8.5 with coverage ≥ 96%;
      bridge gates green with recorded MSI ≥ 80 and CI running.
- [x] Goldens byte-identical throughout; public APIs additive-only.
- [x] AGENTS.md carries the instantiation/ordering exemption ledger.
- [x] `docs/REVIEW.md` annotated with a per-F-id resolution column linking to
      R4 steps (mirroring how REVIEW_3/4 closures were recorded).
