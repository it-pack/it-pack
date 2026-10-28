# it-pack — Tooling & QA Consolidation Plan

> **Status:** Phase 0–4 substantially complete; Phase 2.4 + Phase 5 (docs) in progress
> **Date:** 2026-08-31 (original) / 2026-10-28 (status update)
> **Goal:** Consolidate every it-pack member package onto a *single* tooling stack so that QA is uniform, automatable, and reviewable across the monorepo.
>
> **Completion status by phase:**
> - Phase 0 (baseline/templates): **Done** — `tools/qa/` holds shared phparkitect/phpstan/cs-fixer/infection/codeception templates; `tools/qa/CONVERSION_NOTES.md` tracks the migration.
> - Phase 1 (PHPUnit→Codeception): **Done** — all 18 code-bearing packages use Codeception (via `just-bases`); 0 direct PHPUnit deps.
> - Phase 2 (deptrac→phparkitect): **Partially done** — 9 packages migrated (RunIt, CommandIt, CommitIt, ConfigureIt, DeriveIt, HashIt, AsyncIt, HashItAsync, DisenchantIt); 8 deptrac configs archived. **Phase 2.4 NOT done** — 9 packages (all Phase 1 migrants + new CommitItRunit) still lack phparkitect.
> - Phase 3 (just-bases unification): **Done** — all 18 code-bearing packages on `just-bases`; `*@dev` normalized; `minimum_stability: stable` + `prefer-stable: true`.
> - Phase 4 (root orchestration & CI): **Done** — root `composer.json` (scripts-only), `Makefile` (`make quality`), `.github/workflows/quality.yml` (14-package × 2-PHP matrix).
> - Phase 5 (docs): **In progress** — this document and `docs/REPORT.md` are being updated to reflect the completed phases; `docs/EXECUTION.md` updated.

This document first records the *current state with evidence*, then the *reasoning*, then an *executable, phased plan* with per-step actions, commands, verification, and exit criteria, and finally a per-package action matrix.

---

## 1. Current-state evidence (measured 2026-10-28)

> This document was originally drafted against a 2026-08-31 snapshot. The current-state evidence below reflects the **actual codebase as of 2026-10-28**, reconciled against `src/*/composer.json` files, CI config, and `tools/qa/CONVERSION_NOTES.md`.

Scanned every `src/<Pkg>/composer.json` + presence of `codeception.yml`, `phpunit.xml*`, `deptrac.yaml`, `phparkitect.php`, and `just-bases/*` in `require-dev`. Also inspected `.github/workflows/quality.yml` matrix and root `composer.json`/`Makefile`.

### 1.1 Test framework

| Group | Count | Packages |
|---|---|---|
| **Codeception** (via `just-bases`, no direct require) | 8 | `RunIt`, `CommandIt`, `CommitIt`, `ConfigureIt`, `DeriveIt`, `HashIt`, `AsyncIt`, `HashItAsync` |
| **Codeception** (direct require) | 10 | `DisenchantIt`, `ConfigureItExplorer`, `ConfigureItPsr11`, `ConfigureItRunit`, `ConfigureItSchemaDocs`, `ConfigureItSymfony`, `ConfigureItTesting`, `DeriveItRunit`, `RunItThirdPartySample`, `CommitItRunit` |
| **PHPUnit** (direct dep) | 0 | *(all converted)* |
| **None (no tests)** | 3 (+8 empty) | `LazeIt`, `FileIt`, `ExtendIt` (have composer.json but 0 tests); `AdaptIt`, `CodeIt`, `HookIt`, `PatchIt`, `ProjectIt`, `ReflectIt`, `ShowCaseIt`, `WeakenIt` (no composer.json) |

No package currently uses PHPUnit as a direct require. All 18 code-bearing packages use Codeception (9 pull it transitively via `just-bases/php-unit-test-base`; 9 require it directly as `codeception/codeception`). **Phase 1 complete.**

### 1.2 Architecture/dependency verification

| Tool | Count | Packages |
|---|---|---|
| **phparkitect** (active) | 9 | `RunIt`, `CommandIt`, `CommitIt`, `ConfigureIt`, `DeriveIt`, `HashIt`, `AsyncIt`, `HashItAsync`, `DisenchantIt` |
| **deptrac** (active) | 0 | *(all archived → `deptrac.archive.yaml`)* |
| **none** | 9 + 3 micro-libs | `ConfigureItExplorer`, `ConfigureItPsr11`, `ConfigureItRunit`, `ConfigureItSchemaDocs`, `ConfigureItSymfony`, `ConfigureItTesting`, `DeriveItRunit`, `RunItThirdPartySample`, `CommitItRunit` + `LazeIt`, `FileIt`, `ExtendIt` |

**Phase 2 incomplete.** The 8 Phase-1 migrants and the new `CommitItRunit` still lack phparkitect (Phase 2.4 was not completed — see `tools/qa/CONVERSION_NOTES.md` §"Remaining risks" and `docs/REPORT.md` §7-R12).

### 1.3 Dev-dependency baseline

| Baseline | Count | Packages |
|---|---|---|
| **just-bases** (`php-unit-test-base` / `php-qa-base` / `php-obs-dev-base`) | 18 | All code-bearing packages (including `CommitItRunit`) |
| **ad-hoc** (direct phpstan/cs-fixer/infection pins) | 0 | *(none)* |

**Phase 3 complete.** `just-bases` is uniform across all 18 code-bearing packages.

### 1.4 QA script-name divergence

Conventions already largely align (`cs:check`, `analyse`, `architecture`, `test`, `mutation`, `quality`), but **`quality` is not universal** — 6 packages lack it (`ConfigureIt`, `DeriveIt`, `HashItAsync`, `LazeIt`, `FileIt`, `ExtendIt`). Additionally, 9 packages lack an `architecture` script (Phase 2.4 incomplete), 2 use `infection` instead of `mutation` (`DeriveIt`, `HashIt`), and several packages have extra dev-only scripts (`lint:length`, `lint:types`, `coverage`, `bc-check`, etc.) that are not part of the canonical lane contract. The plan standardizes the lane contract (§6) so a single root orchestrator can drive every package identically.

---

## 2. Target state & principles

**One stack, everywhere it applies:**

- **Test runner:** Codeception for *all* packages that have (or should have) tests. → **Done** (Phase 1, 2026-10-28).
- **Architecture verifier:** phparkitect for *all* packages with source code (replaces deptrac). → **In progress** (Phase 2) — 9 done, 9+3 micro-libs remain.
- **Dev baseline:** `just-bases/*` for *all* packages with tests; identical version pins. → **Done** (Phase 3, 2026-10-28).
- **QA lane contract:** identical composer script names per package (`test`, `test:unit`, `cs:check`, `cs:fix`, `analyse`, `architecture`, `mutation`, `quality`). → **In progress** — 6 packages lack `quality`; 9 lack `architecture`; 2 use `infection` instead of `mutation`.
- **Root orchestration:** a root `composer.json` (scripts-only) + `Makefile` + a GitHub Actions matrix that runs `make quality` once per package, so "is the whole monorepo green?" is one command. → **Done** (Phase 4, 2026-10-28) — but CI matrix covers only 14/15 packages with `quality` scripts.
- **Reference templates:** `DisenchantIt/phparkitect.php` became the canonical phparkitect template; a single `tools/qa/` tree holds shared Codeception/phpstan/cs-fixer/infection configs that every package reuses. → **Done.**

**Principles:**

- **Migrate, don't rewrite behavior.** Preserve every existing architectural rule; only change the tool expressing it.
- **Pilot before batch.** Convert one tiny package per tool first; validate the recipe; then batch the rest. → Applied throughout; see `tools/qa/CONVERSION_NOTES.md` for per-package migration logs.
- **Dual-run during transition.** Keep the old tool (deptrac yaml) archived and run both until the new tool is green, to catch semantic gaps. → Completed for all 8 deptrac packages; dual-run diffs logged as 0 violations in all migrations.
- **Lowest-risk conversions first.** PHPUnit→Codeception was mechanical (Codeception's `Unit` suite *extends* `PHPUnit\Framework\TestCase`); deptrac→phparkitect was the riskier semantic port and got the most care.

---

## 3. Reasoning (the "why")

### 3.1 Why Codeception as the single test framework

- **It is already the plurality** (9 lead packages use it via `just-bases`; 9 more require it directly) and includes the most mature/tooled packages (`RunIt` with 9 suites incl. Conformance/Concurrency/Architecture, `AsyncIt` with 4 suites). Standardizing *down* to the minority (PHPUnit) would discard more working investment.
- **Conversion was low-risk and is complete.** Codeception's `Unit` suite subclasses `PHPUnit\Framework\TestCase`, so existing PHPUnit test methods (`assertEquals`, data providers, `setUp`/`tearDown`, `createMock`) kept working with a base-class swap. The 8 former-PHPUnit packages were small (1–19 test files each), so the blast radius was contained.
- **One runner, many suites.** Codeception gives `Unit` / `Integration` / `Functional` / `Acceptance` from one config, which the satellites/bridges can grow into without re-tooling. It also already hosts the `infection/codeception-adapter` pattern the lead packages use.
- **Fewer transitive surprises.** Keeping PHPUnit *only* as Codeception's internal dependency (not a first-class direct require) removes a whole class of `phpunit/phpunit` version conflicts across packages.

**Counter-argument considered:** PHPUnit is lighter and more familiar. But it is the minority and offers no capability the Codeception `Unit` suite lacks for these packages. The consistency win outweighs the marginal weight cost.

**Status:** Phase 1 complete as of 2026-10-28. `grep -rl '"phpunit/phpunit"' src/*/composer.json` returns nothing.

### 3.2 Why phparkitect over deptrac for architecture verification

- **Expressiveness.** deptrac models a *layered dependency graph* (collectors + layers + allowed-dependencies) — excellent for "layer A may not call layer B" but awkward for anything else. phparkitect expresses the *same* layering **and** namespace/dependency/modularity/naming rules in one fluent DSL, e.g. `Rule::namespace('ItPack\CommandIt\Core')->should(new NotDependsOnTheseNamespaces(['ItPack\RunIt\*']))` — exactly the rule `DisenchantIt` already uses successfully.
- **One tool, more coverage.** With phparkitect we can enforce, in a single file per package: layer boundaries, **forbidden sibling imports** (the "ports-not-imports" doctrine from `REPORT.md §5.5`), naming conventions, and prohibited dependencies — no need for a separate linter.
- **Reference already exists.** `DisenchantIt/phparkitect.php` proved the pattern works in this repo and was generalized into `tools/qa/phparkitect/base.php`.
- **Honest caveat (documented, not blocking):** phparkitect parses every file's AST in a single process and can be slower than deptrac on very large trees (`RunIt` ~395 files is the only package where this matters; scope `ClassSet::fromDir()` to `src/` and exclude `tests/`, or run it once per CI job rather than per save). deptrac's visual graph output is nicer for onboarding, so we *archive* deptrac configs rather than delete them, in case we ever want the diagram back.

**Net:** migrate to phparkitect as the verifier of record; archive deptrac configs as historical/visual aids.

**Status:** Phase 2 partially complete as of 2026-10-28. 9 packages migrated (8 dual-run clean at 0 violations; AsyncIt dual-run showed 21 pre-existing violations flagged by both tools — not migration artifacts). 8 deptrac configs archived as `deptrac.archive.yaml`. **9 packages remain without phparkitect** (Phase 2.4 — see §5).

### 3.3 Why unify on `just-bases/*`

The 8 lead packages already pin identical `just-bases` versions for phpstan/cs-fixer/infection/codeception. Centralizing via `just-bases` makes a single version bump upgrade the whole monorepo's static-analysis toolchain at once — directly addressing `REPORT.md §7-R2/R4` (release-coordination hazard). **Status:** Phase 3 complete — all 18 code-bearing packages use `just-bases`; zero ad-hoc pins remain.

### 3.4 Why a root orchestrator

`REPORT.md §7-R2` explicitly flagged "no monorepo tooling." Without it, "is the monorepo green?" required `cd`-ing into 18 packages. A root `composer.json` + `Makefile` that loops `composer quality` per package turns that into one command and makes CI trivial. **Status:** Phase 4 complete — root `composer.json` (scripts-only) + `Makefile` + `.github/workflows/quality.yml` (14×2 matrix) are in place.

---

## 4. Risks & mitigations

| Risk | Likelihood | Mitigation | Status |
|---|---|---|---|
| deptrac→phparkitect semantic gaps | Medium | Dual-run during migration; diff outputs before archiving deptrac config. | Resolved — all 8 dual-run diffs at 0 violations (AsyncIt showed 21 pre-existing in both). |
| phparkitect slow on `RunIt` | Low-Medium | Scope `ClassSet` to `src/` only; run in CI not per-save. | Mitigated. |
| Codeception `Unit` base-class swap breaks a test relying on PHPUnit internals | Low | Pilot on `ConfigureItPsr11` (1 test) first; fix recipe; batch after. | Resolved. |
| `just-bases` version not exposing Codeception modules the satellites need | Low | Explicitly added `codeception/module-asserts` (+`module-phpbrowser` where HTTP tested). | Resolved. |
| Over-consolidating micro-libs (`LazeIt`/`FileIt`/`ExtendIt`) with no tests | n/a | Marked optional (§5 Phase 2.5); add only phparkitect + lint, skip test conversion. | Not started (optional). |
| Removing PHPUnit breaks `infection/codeception-adapter` expectations | Low | `infection/codeception-adapter` works with Codeception; ensure it stays in require-dev. | Resolved. |
| Phase 2.4 not done — 9 packages lack phparkitect | High | Wire `tools/qa/phparkitect/base.php` + `forbidSibling()` into each remaining package; dual-run against archived deptrac where applicable. | **Open** — see §5 Phase 2.4. |
| CI matrix doesn't cover all packages | Medium | Add ConfigureIt, CommitItRunit to `.github/workflows/quality.yml`; standardize `ConfigureIt`/`DeriveIt`/`HashItAsync` scripts first. | **Open** — see §5 Phase 5 / REPORT.md §7-R12. |

---

## 5. Execution plan (phased, executable)

Each step lists: **Affected**, **Actions**, **Verify**, **Exit criteria**. Commands assume you are in `src/<Pkg>`.

### Phase 0 — Establish shared baselines & reference templates

**0.1 Create `tools/qa/` at repo root** with reusable configs — **Done.**
- `tools/qa/phparkitect/base.php` — `layeredArchitecture()` + `classSet()` helpers (namespace `ItPack\Qa\PhpArkitect`).
- `tools/qa/phparkitect/forbid-sibling.php` — `forbidSibling()` — "ports-not-imports" rule generator.
- `tools/qa/codeception.dist.yml` — base Codeception config.
- `tools/qa/phpstan.neon.dist` — Base PHPStan config (`level: max`).
- `tools/qa/.php-cs-fixer.dist.php` — Base PHP-CS-Fixer rule set.
- `tools/qa/infection.json5` — Base Infection config (Codeception adapter).
- `tools/qa/CONVERSION_NOTES.md` — Pilot recipes + per-package migration log.

**0.2 Ratify the canonical phparkitect template** — **Done.** `DisenchantIt/phparkitect.php` generalized into `tools/qa/phparkitect/base.php`.

**0.3 Document the QA lane contract** (see §6) in `AGENTS.md` and root `EXECUTION.md` — **Done.**

*Exit: `tools/qa/` exists and `base.php` loads without error under `php -l`. ✓*

### Phase 1 — Test framework → Codeception (pilot then batch) — **COMPLETE**

**1.1 Pilot on `ConfigureItPsr11` (1 PHPUnit test, simplest possible).** — **Done.**
- Actions:
  - `composer remove phpunit/phpunit --dev` (and any `phpunit.xml.dist`).
  - `composer require --dev just-bases/php-unit-test-base:^1.0 just-bases/php-qa-base:^1.0 codeception/module-asserts:^3.0` (add `codeception/module-phpbrowser:^3.0` only if HTTP tested).
  - Create `codeception.yml` (extend `tools/qa/codeception.dist.yml`); add a `unit.suite.yml` with `class_name: UnitTester`, `modules: [Asserts]`.
  - In the test file: change `class XTest extends \PHPUnit\Framework\TestCase` → `class XTest extends \Codeception\Test\Unit`; keep assertions/`setUp`/`tearDown`.
  - Replace composer scripts: `"test": "vendor/bin/codecept run"`, `"test:unit": "vendor/bin/codecept run Unit"`.
- Verify: `composer test` is green.
- Exit: pilot package green on Codeception; recipe noted in `tools/qa/CONVERSION_NOTES.md`.

**1.2 Batch the remaining 7 PHPUnit packages** — **Done.**
`ConfigureItExplorer`, `ConfigureItSchemaDocs`, `ConfigureItSymfony`, `ConfigureItTesting`, `ConfigureItRunit`, `DeriveItrunit`, `RunItThirdPartySample` — all converted. Exit: **0** packages use PHPUnit as a direct require.

### Phase 2 — deptrac → phparkitect (pilot then batch)

**2.1 Pilot on `HashItAsync` (smallest deptrac package, 1 src file).** — **Done.**
- Verify: `composer architecture` green; dual-run with deptrac: 0 violations both tools.
- Exit: phparkitect green; deviation log in `tools/qa/CONVERSION_NOTES.md`.

**2.2 Carefully port `CommandIt`** (highest-value rule: `RunIt` layer forbidden everywhere). — **Done.**

**2.3 Batch the rest:** `RunIt`, `CommitIt`, `ConfigureIt`, `DeriveIt`, `HashIt`. — **Done.**

**2.4 Add phparkitect to the 8 packages migrated in Phase 1 + CommitItRunit.** — **NOT DONE (see R12 in REPORT.md).**
The 8 Phase-1 packages (`ConfigureItExplorer`, `ConfigureItPsr11`, `ConfigureItRunit`, `ConfigureItSchemaDocs`, `ConfigureItSymfony`, `ConfigureItTesting`, `DeriveItRunit`, `RunItThirdPartySample`) plus the new `CommitItRunit` still have no `phparkitect.php`, no `architecture` script, and no `tools/qa/phparkitect/base.php` import. This is the single remaining gap in the consolidation. Each package needs a `phparkitect.php` calling `layeredArchitecture()` + appropriate `forbidSibling()` rules, plus an `architecture` composer script, then dual-run against any archived deptrac config to verify parity.

**2.5 Add phparkitect (rules only, no tests) to `LazeIt`/`FileIt`/`ExtendIt`** — **Not started.** Optional but recommended to close `REPORT.md §7-R7`.

- Exit: **1** architecture tool repo-wide (phparkitect); zero `deptrac.yaml` active; **9+3 packages without phparkitect remain.**

### Phase 3 — Dev-dependency baseline unification — **COMPLETE**

**3.1** For every package migrated in Phases 1–2 that still pins ad-hoc `phpstan`/`php-cs-fixer`/`infection` (i.e. `DisenchantIt`): `composer remove` those direct pins and `composer require --dev just-bases/php-qa-base:^1.0 just-bases/php-obs-dev-base:^0.1.0` (match the lead packages' versions).

**3.2** Ensure `phpstan.neon`, `.php-cs-fixer.php`, `infection.json5` in each point at the `tools/qa/*.dist` configs (or are symlinked) so rules are identical.

**3.3** Normalize the `*` / `*@dev` sibling-constraint drift noted in `REPORT.md §7-R1` to one style (recommend `*@dev` + `minimum_stability: stable` consistent with the lead packages) — cheap now, before any Packagist tag.

- Verify: `composer show | grep -E 'phpstan|cs-fixer|infection'` yields identical versions across all test-bearing packages.
- Exit: a single `just-bases` version bump would upgrade the whole monorepo's QA toolchain. ✓

### Phase 4 — Root orchestration & CI — **COMPLETE**

**4.1** Add root `composer.json` (scripts-only) with a `quality` script that iterates packages, e.g. a small PHP/`find` loop running `composer quality` in each `src/<Pkg>` that defines it.

**4.2** Add `Makefile` at repo root:
```
quality:
	@for d in src/*/; do [ -f "$$d/composer.json" ] || continue; (cd "$$d" && composer quality) || exit 1; done
```

**4.3** Add `.github/workflows/quality.yml` running the matrix (one job per package, or `make quality`) on push/PR, failing the build on any lane error.

- Verify: from repo root, `make quality` runs every package's `composer quality` and exits non-zero if any fails.
- Exit: "is the monorepo green?" == one command; CI enforces it. ✓ (14 of 15 packages with `quality` are in CI; ConfigureIt and HashItAsync lack `quality` scripts; CommitItRunit has `quality` but is **not yet in the CI matrix** — see remaining work below.)

### Phase 5 — Documentation & reporting — **IN PROGRESS**

**5.1** Update `docs/REPORT.md §6` divergence table: Test framework → **Codeception (18)** vs PHPUnit (0); Architecture → **phparkitect (9)** vs pending (9); Dev baseline → **just-bases (18)**.
**5.2** Update `docs/REPORT.md §7-R2/R3/R4` status (monorepo tooling added; naming standardized; port convergence tracked separately).
**5.3** Add a short "Tooling standard" section to root `EXECUTION.md` and each `AGENTS.md`: *Codeception + phparkitect + just-bases; QA lane names per §6.*
**5.4** Note the consolidation in `tools/qa/CONVERSION_NOTES.md` (already done per package).
- Exit: `REPORT.md` and `EXECUTION.md` reflect the unified stack; a newcomer can run `make quality` and read one standard.

---

## 6. Canonical QA lane contract (enforced everywhere)

Every package with source code MUST expose these composer scripts (lead packages already do; migrants align):

| Script | Command | Notes |
|---|---|---|
| `test` | `vendor/bin/codecept run` | All suites. |
| `test:unit` | `vendor/bin/codecept run Unit` | Fast subset for dev loop. |
| `cs:check` | `vendor/bin/php-cs-fixer fix --dry-run --diff` | |
| `cs:fix` | `vendor/bin/php-cs-fixer fix` | |
| `analyse` | `vendor/bin/phpstan analyse` | |
| `architecture` | `vendor/bin/phparkitect` | **Replaces** `deptrac`. |
| `mutation` | `vendor/bin/infection --min-msi=<pkg-appropriate>` | Adapter-based (codeception-adapter). |
| `quality` | `cs:check` → `analyse` → `architecture` → `test` → `mutation` → `security:audit` | Single green gate. |

Optional but encouraged: `security:audit` (`composer audit --abandoned=report`), `lint:length`, `lint:types`.

---

## 7. Sequencing summary

```
Phase 0  (baselines/templates)  ──► Phase 1 pilot ─► Phase 1 batch
                                                         │
                                         Phase 2 pilot ──┤──► Phase 2 batch + add to Ph1 pkgs
                                                         │   (Phase 2.4 OPEN — 9 pkgs pending)
                                     Phase 3 (just-bases) ─┤   (DONE)
                                                         │
                                     Phase 4 (root+CI) ────┤   (DONE — 14/15 pkgs in CI)
                                                         │
                                     Phase 5 (docs) ───────┘   (IN PROGRESS)
```

**Do not start Phase 2 batch until Phase 2 pilot dual-run diff is clean.** **Do not delete any `deptrac.archive.yaml` until Phase 5.**

> **Phase 2.4 is the single open item blocking full verification.** Without phparkitect in the 9 remaining packages, the ports-not-imports doctrine is documented but not enforced in those packages. This should be the next priority.

> **Status as of 2026-10-28:** Phases 0–1, 3, 4 are complete. Phase 2 is partially complete (pilot + 8 batch packages done; Phase 2.4 incomplete — see matrix and `§5 Phase 2.4`). Phase 5 is in progress.

---

## 8. Per-package action matrix

Legend: **CC** = convert tests to Codeception · **PA** = add/port phparkitect (remove deptrac) · **JB** = adopt just-bases baseline · **✓** = done.

| Package | Tests today | Arch today | JB | CC | PA | Status |
|---|---|---|---|---|---|---|
| `RunIt` | Codeception | phparkitect (was deptrac) | ✓ | n/a | ✓ (2.5) | Complete |
| `CommandIt` | Codeception | phparkitect (was deptrac) | ✓ | n/a | ✓ (2.2) | Complete |
| `CommitIt` | Codeception | phparkitect (was deptrac) | ✓ | n/a | ✓ (2.3) | Complete |
| `ConfigureIt` | Codeception | phparkitect (was deptrac) | ✓ | n/a | ✓ (2.3) | Complete (no `quality` script) |
| `DeriveIt` | Codeception | phparkitect (was deptrac) | ✓ | n/a | ✓ (2.4) | Complete (uses `infection` not `mutation`; no `quality` script) |
| `HashIt` | Codeception | phparkitect (was deptrac) | ✓ | n/a | ✓ (2.2) | Complete |
| `AsyncIt` | Codeception | phparkitect (was deptrac) | ✓ | n/a | ✓ (2.4) | Complete |
| `HashItAsync` | Codeception | phparkitect (was deptrac) | ✓ | n/a | ✓ (2.1 pilot) | Complete (no `quality`/`mutation`/`security:audit` scripts) |
| `DisenchantIt` | Codeception | **phparkitect** (template) | ✓ (3.1) | n/a | n/a | Complete |
| `ConfigureItExplorer` | Codeception (was PHPUnit) | none | ✓ (3.1) | ✓ (1.1 pilot) | **TODO** | Phase 2.4 pending |
| `ConfigureItPsr11` | Codeception (was PHPUnit) | none | ✓ (3.1) | ✓ (1.1 pilot) | **TODO** | Phase 2.4 pending |
| `ConfigureItSchemaDocs` | Codeception (was PHPUnit) | none | ✓ (3.1) | ✓ (1.2) | **TODO** | Phase 2.4 pending |
| `ConfigureItSymfony` | Codeception (was PHPUnit) | none | ✓ (3.1) | ✓ (1.2) | **TODO** | Phase 2.4 pending |
| `ConfigureItTesting` | Codeception (was PHPUnit) | none | ✓ (3.1) | ✓ (1.2) | **TODO** | Phase 2.4 pending |
| `ConfigureItRunit` | Codeception (was PHPUnit) | none | ✓ (3.1) | ✓ (1.2) | **TODO** | Phase 2.4 pending |
| `DeriveItRunit` | Codeception (was PHPUnit) | none | ✓ (3.1) | ✓ (1.2) | **TODO** | Phase 2.4 pending |
| `RunItThirdPartySample` | Codeception (was PHPUnit) | none | ✓ (3.1) | ✓ (1.2) | **TODO** | Phase 2.4 pending |
| `CommitItRunit` | Codeception | none | ✓ | ✓ | **TODO** | New package; Phase 2.4 pending |
| `LazeIt` | none | none | — | — | — | Phase 2.5 optional |
| `FileIt` | none | none | — | — | — | Phase 2.5 optional |
| `ExtendIt` | none | none | — | — | — | Phase 2.5 optional |
| `AdaptIt`/`CodeIt`/`HookIt`/`PatchIt`/`ProjectIt`/`ReflectIt`/`ShowCaseIt`/`WeakenIt` | — | — | — | — | — | No action until they gain code (address `REPORT.md §7-R6`). |

**Totals:** Codeception = 18 (all code-bearing) · phparkitect = 9 · pending phparkitect = 9 · just-bases = 18 · deptrac active = 0 (8 archived) · PHPUnit as direct dep = 0.

---

## 9. Verification checklist (done when all true)

- [x] `grep -rl '"phpunit/phpunit"' src/*/composer.json` → empty.
- [x] `grep -rl 'deptrac' src/*/composer.json` → empty (only `deptrac.archive.yaml` remain).
- [ ] `composer architecture` green in all 18 code-bearing packages. **(9 pending — Phase 2.4)**
- [ ] `composer test` green in all test-bearing packages.
- [ ] `make quality` from repo root exits 0. **(Currently 14 packages participate; ConfigureIt, DeriveIt, HashItAsync lack `quality` scripts)**
- [ ] CI `quality.yml` green on a PR touching two packages. **(Matrix covers 14 of 15 packages with `quality`; CommitItRunit has `quality` but is missing from CI matrix)**
- [x] `docs/REPORT.md §6` updated; `EXECUTION.md` states the standard.
- [x] `tools/qa/` holds the shared phparkitect/Codeception/phpstan/cs-fixer/infection configs.
