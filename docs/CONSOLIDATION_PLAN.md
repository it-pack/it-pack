# it-pack — Tooling & QA Consolidation Plan

> **Status:** Draft for execution
> **Date:** 2026-08-31
> **Goal:** Consolidate every it-pack member package onto a *single* tooling stack so that QA is uniform, automatable, and reviewable across the monorepo.
> **Mandated decisions (from maintainer):**
> 1. **Test framework = Codeception** (used by 9/17 test-bearing packages today; the PHPUnit minority must convert).
> 2. **Architecture/dependency verifier = phparkitect** (used by 1 package today; the 8 `deptrac` packages must migrate; phparkitect is judged the more expressive/powerful tool).

This document first records the *current state with evidence*, then the *reasoning*, then an *executable, phased plan* with per-step actions, commands, verification, and exit criteria, and finally a per-package action matrix.

---

## 1. Current-state evidence (measured 2026-08-31)

Scanned every `src/<Pkg>/composer.json` + presence of `codeception.yml`, `phpunit.xml*`, `deptrac.yaml`, `phparkitect.php`, and `just-bases/*` in `require-dev`.

### 1.1 Test framework

| Group | Count | Packages |
|---|---|---|
| **Codeception** | 9 | `RunIt`, `CommandIt`, `CommitIt`, `ConfigureIt`, `DeriveIt`, `DisenchantIt`, `HashIt`, `AsyncIt`, `HashItAsync` |
| **PHPUnit** | 8 | `ConfigureItExplorer`, `ConfigureItPsr11`, `ConfigureItSchemaDocs`, `ConfigureItSymfony`, `ConfigureItTesting`, `ConfigureItRunit`, `DeriveItRunit`, `RunItThirdPartySample` |
| **None (no tests)** | 3 (+8 empty) | `LazeIt`, `FileIt`, `ExtendIt` (have composer.json but 0 tests); `AdaptIt`, `CodeIt`, `HookIt`, `PatchIt`, `ProjectIt`, `ReflectIt`, `ShowCaseIt`, `WeakenIt` (no composer.json) |

No package currently mixes both frameworks — clean conversion targets.

### 1.2 Architecture/dependency verification

| Tool | Count | Packages |
|---|---|---|
| **deptrac** | 8 | `RunIt`, `CommandIt`, `CommitIt`, `ConfigureIt`, `DeriveIt`, `HashIt`, `AsyncIt`, `HashItAsync` |
| **phparkitect** | 1 | `DisenchantIt` (reference implementation; contains the `Core must not depend on ItPack\RunIt\*` rule at `src/DisenchantIt/phparkitect.php:233-239`) |
| **none** | 11 | the 8 PHPUnit packages + 3 micro-libs |

### 1.3 Dev-dependency baseline

| Baseline | Count | Packages |
|---|---|---|
| **just-bases** (`php-unit-test-base` / `php-qa-base` / `php-obs-dev-base`) | 8 | `RunIt`, `CommandIt`, `CommitIt`, `ConfigureIt`, `DeriveIt`, `HashIt`, `AsyncIt`, `HashItAsync` |
| **ad-hoc** (`phpunit` + `phpstan` + `php-cs-fixer` + `infection` pinned directly) | 9 | `DisenchantIt` + the 8 PHPUnit packages |

So the *de-facto* "lead" stack is already: **Codeception + phparkitect + just-bases**. The consolidation is mostly about dragging the PHPUnit/ad-hoc minority up to that lead, not inventing something new.

### 1.4 QA script-name divergence (even among Codeception packages)

Conventions already largely align (`cs:check`, `analyse`, `architecture`, `test`, `mutation`, `quality`), but `quality` is not universal and a few packages name suites differently. The plan standardizes the lane contract (§6) so a single root orchestrator can drive every package identically.

---

## 2. Target state & principles

**One stack, everywhere it applies:**

- **Test runner:** Codeception for *all* packages that have (or should have) tests.
- **Architecture verifier:** phparkitect for *all* packages with source code (replaces deptrac).
- **Dev baseline:** `just-bases/*` for *all* packages with tests; identical version pins.
- **QA lane contract:** identical composer script names per package (`test`, `cs:check`, `analyse`, `architecture`, `mutation`, `quality`).
- **Root orchestration:** a root `composer.json` (scripts-only) + `Makefile` + a GitHub Actions matrix that runs `make quality` once per package, so "is the whole monorepo green?" is one command.
- **Reference templates:** `DisenchantIt/phparkitect.php` becomes the canonical phparkitect template; a single `tools/qa/` tree holds shared Codeception/phpstan/cs-fixer/infection configs that every package reuses.

**Principles:**

- **Migrate, don't rewrite behavior.** Preserve every existing architectural rule; only change the tool expressing it.
- **Pilot before batch.** Convert one tiny package per tool first; validate the recipe; then batch the rest.
- **Dual-run during transition.** Keep the old tool (deptrac yaml) archived and run both until the new tool is green, to catch semantic gaps.
- **Lowest-risk conversions first.** PHPUnit→Codeception is mechanical (Codeception's `Unit` suite *extends* `PHPUnit\Framework\TestCase`); deptrac→phparkitect is the riskier semantic port and gets the most care.

---

## 3. Reasoning (the "why")

### 3.1 Why Codeception as the single test framework

- **It is already the plurality (9 vs 8)** and includes the most mature/tooled packages (`RunIt` with 9 suites incl. Conformance/Concurrency/Architecture, `AsyncIt` with 4 suites). Standardizing *down* to the minority (PHPUnit) would discard more working investment.
- **Conversion is low-risk.** Codeception's `Unit` suite subclasses `PHPUnit\Framework\TestCase`, so existing PHPUnit test methods (`assertEquals`, data providers, `setUp`/`tearDown`, `createMock`) keep working with a one-line base-class swap. The 8 PHPUnit packages are small (1–19 test files each), so the blast radius is contained.
- **One runner, many suites.** Codeception gives `Unit` / `Integration` / `Functional` / `Acceptance` from one config, which the satellites/bridges can grow into without re-tooling. It also already hosts the `infection/codeception-adapter` pattern the lead packages use.
- **Fewer transitive surprises.** Keeping PHPUnit *only* as Codeception's internal dependency (not a first-class direct require) removes a whole class of `phpunit/phpunit` version conflicts across 8 packages.

**Counter-argument considered:** PHPUnit is lighter and more familiar. But it is the minority and offers no capability the Codeception `Unit` suite lacks for these packages. The consistency win outweighs the marginal weight cost.

### 3.2 Why phparkitect over deptrac for architecture verification

- **Expressiveness.** deptrac models a *layered dependency graph* (collectors + layers + allowed-dependencies) — excellent for "layer A may not call layer B" but awkward for anything else. phparkitect expresses the *same* layering **and** namespace/dependency/modularity/naming rules in one fluent DSL, e.g. `Rule::namespace('ItPack\CommandIt\Core')->should(new NotDependsOnTheseNamespaces(['ItPack\RunIt\*']))` — exactly the rule `DisenchantIt` already uses successfully.
- **One tool, more coverage.** With phparkitect we can enforce, in a single file per package: layer boundaries, **forbidden sibling imports** (the "ports-not-imports" doctrine from `REPORT.md §5.5`), naming conventions, and prohibited dependencies — no need for a separate linter.
- **Reference already exists.** `DisenchantIt/phparkitect.php` proves the pattern works in this repo and is the natural template.
- **Honest caveat (documented, not blocking):** phparkitect parses every file's AST in a single process and can be slower than deptrac on very large trees (`RunIt` ~395 files is the only package where this matters; if it becomes a problem, scope `ClassSet::fromDir()` to `src/` and exclude `tests/`, or run it once per CI job rather than per save). deptrac's visual graph output is nicer for onboarding, so we *archive* deptrac configs rather than delete them, in case we ever want the diagram back.

**Net:** migrate to phparkitect as the verifier of record; archive deptrac configs as historical/visual aids.

### 3.3 Why unify on `just-bases/*`

The 8 lead packages already pin identical `just-bases` versions for phpstan/cs-fixer/infection/codeception. The 9 ad-hoc packages pin them by hand with drift (`phpstan ^2` vs `^1`, cs-fixer `^3.0` vs `^3.64`, infection `*` vs `^0.35`). Centralizing via `just-bases` makes a single version bump upgrade the whole monorepo's static-analysis toolchain at once — directly addressing `REPORT.md §7-R2/R4` (release-coordination hazard).

### 3.4 Why a root orchestrator

`REPORT.md §7-R2` explicitly flags "no monorepo tooling." Without it, "is the monorepo green?" requires `cd`-ing into 17 packages. A root `composer.json` + `Makefile` that loops `composer quality` per package turns that into one command and makes CI trivial.

---

## 4. Risks & mitigations

| Risk | Likelihood | Mitigation |
|---|---|---|
| deptrac→phparkitect semantic gaps (a rule that deptrac enforced but phparkitect misses) | Medium | Dual-run both during transition (§5 Phase 2 step 4); diff outputs before deleting deptrac config. |
| phparkitect slow on `RunIt` | Low-Medium | Scope `ClassSet` to `src/` only; run in CI not per-save; allow `@architecture` to be the slow lane. |
| Codeception `Unit` base-class swap breaks a test relying on PHPUnit internals | Low | Pilot on `ConfigureItPsr11` (1 test) first; fix recipe; batch after. |
| `just-bases` version not exposing Codeception modules the satellites need | Low | Explicitly add `codeception/module-asserts` (+`module-phpbrowser` where HTTP is tested), mirroring `DisenchantIt`/`AsyncIt` require-dev. |
| Over-consolidating micro-libs (`LazeIt`/`FileIt`/`ExtendIt`) with no tests | n/a | Marked optional (§5 Phase 3 step 5); add only phparkitect + lint, skip test conversion. |
| Removing PHPUnit breaks `infection/codeception-adapter` expectations | Low | `infection/codeception-adapter` works with Codeception; ensure it stays in require-dev. |

---

## 5. Execution plan (phased, executable)

Each step lists: **Affected**, **Actions**, **Verify**, **Exit criteria**. Commands assume you are in `src/<Pkg>`.

### Phase 0 — Establish shared baselines & reference templates

**0.1 Create `tools/qa/` at repo root** with reusable configs:
- `tools/qa/phparkitect/base.php` — a function returning common rules (layer components Domain/Contract/Application/SDK/Adapter; "Contract must not depend on Adapter"; "SDK must not depend on Adapter"; "Adapter may depend on Contract+Application"). Consumed by every package's `phparkitect.php`.
- `tools/qa/codeception.dist.yml` — base Codeception config (paths, settings, `support` bootstrap) packages copy/extend.
- `tools/qa/phpstan.neon.dist`, `tools/qa/.php-cs-fixer.dist.php`, `tools/qa/infection.json5` — canonical static-analysis configs (seeded from `RunIt`/`AsyncIt` current ones).
- `tools/qa/phparkitect/forbid-sibling.php` — reusable rule generator: `forbidSibling('CommandIt', 'RunIt')` emitting the `NotDependsOnTheseNamespaces` rule (clone of DisenchantIt's).

**0.2 Ratify the canonical phparkitect template** by generalizing `src/DisenchantIt/phparkitect.php` into `tools/qa/phparkitect/base.php` (keep DisenchantIt's as the first consumer).

**0.3 Document the QA lane contract** (see §6) in `AGENTS.md` and root `EXECUTION.md`.

*Exit: `tools/qa/` exists and `base.php` loads without error under `php -l`.*

### Phase 1 — Test framework → Codeception (pilot then batch)

**1.1 Pilot on `ConfigureItPsr11` (1 PHPUnit test, simplest possible).**
- Actions:
  - `composer remove phpunit/phpunit --dev` (and any `phpunit.xml.dist`).
  - `composer require --dev just-bases/php-unit-test-base:^1.0 just-bases/php-qa-base:^1.0 codeception/module-asserts:^3.0` (add `codeception/module-phpbrowser:^3.0` only if HTTP tested).
  - Create `codeception.yml` (extend `tools/qa/codeception.dist.yml`); add a `unit.suite.yml` with `class_name: UnitTester`, `modules: [Asserts]`.
  - In the test file: change `class XTest extends \PHPUnit\Framework\TestCase` → `class XTest extends \Codeception\Test\Unit`; keep assertions/`setUp`/`tearDown`.
  - Replace composer scripts: `"test": "vendor/bin/codecept run"`, `"test:unit": "vendor/bin/codecept run Unit"`.
- Verify: `composer test` is green.
- Exit: pilot package green on Codeception; recipe noted in `tools/qa/CONVERSION_NOTES.md`.

**1.2 Batch the remaining 7 PHPUnit packages** using the pilot recipe:
`ConfigureItExplorer`, `ConfigureItSchemaDocs`, `ConfigureItSymfony`, `ConfigureItTesting`, `ConfigureItRunit`, `DeriveItRunit`, `RunItThirdPartySample`.
- Same actions per package. For packages with `Functional`/`Acceptance` needs (e.g. `ConfigureItExplorer` web, `ConfigureItSymfony` DI), add the matching Codeception suite instead of forcing Unit.
- Verify each: `composer test` green.
- Exit: **0** packages use PHPUnit as a direct require; `grep -rl '"phpunit/phpunit"' src/*/composer.json` returns nothing.

### Phase 2 — deptrac → phparkitect (pilot then batch)

**2.1 Pilot on `HashItAsync` (smallest deptrac package, 1 src file).**
- Actions:
  - Create `phparkitect.php` importing `tools/qa/phparkitect/base.php` + package-specific rules derived from its `deptrac.yaml`.
  - Rename `deptrac.yaml` → `deptrac.archive.yaml` (keep for dual-run).
  - Replace composer script `"architecture": "vendor/bin/deptrac …"` → `"architecture": "vendor/bin/phparkitect"`.
- Verify: `composer architecture` green; then run `vendor/bin/deptrac --config-file=deptrac.archive.yaml` and **diff** that both flag the same violations (if any).
- Exit: phparkitect green; deviation log in `tools/qa/CONVERSION_NOTES.md`.

**2.2 Carefully port `CommandIt`** (highest-value rule: `RunIt` layer forbidden everywhere — `src/CommandIt/deptrac.yaml:45-48,83-85`). Use `tools/qa/phparkitect/forbid-sibling.php` generator so the rule is identical in intent to DisenchantIt's.

**2.3 Batch the rest:** `RunIt`, `CommitIt`, `ConfigureIt`, `DeriveIt`, `HashIt`, `AsyncIt`.
- For each, port layer rules (Domain/Contract/Application/SDK/Adapter) from `deptrac.yaml` into the `Architecture::withComponents()` DSL in `tools/qa/phparkitect/base.php` (parameterized by namespace root), then `phparkitect.php` calls it.
- Archive each `deptrac.yaml` → `deptrac.archive.yaml` after dual-run passes.

**2.4 Add phparkitect to the 8 packages migrated in Phase 1** (they currently have *no* architecture verifier) so every code-bearing package is covered.

**2.5 Add phparkitect (rules only, no tests) to `LazeIt`/`FileIt`/`ExtendIt`** (optional but recommended to close `REPORT.md §7-R7`).

- Verify: `grep -rl 'deptrac' src/*/composer.json` returns nothing; `composer architecture` green in all 17 code-bearing packages.
- Exit: **1** architecture tool repo-wide (phparkitect); zero `deptrac.yaml` active.

### Phase 3 — Dev-dependency baseline unification

**3.1** For every package migrated in Phases 1–2 that still pins ad-hoc `phpstan`/`php-cs-fixer`/`infection` (i.e. the 8 former PHPUnit packages + `DisenchantIt`): `composer remove` those direct pins and `composer require --dev just-bases/php-qa-base:^1.0 just-bases/php-obs-dev-base:^0.1.0` (match the lead packages' versions).
**3.2** Ensure `phpstan.neon`, `.php-cs-fixer.php`, `infection.json5` in each point at the `tools/qa/*.dist` configs (or are symlinked) so rules are identical.
**3.3** Normalize the `*` / `*@dev` sibling-constraint drift noted in `REPORT.md §7-R1` to one style (recommend `*@dev` + `minimum-stability: dev` consistent with the lead packages) — cheap now, before any Packagist tag.
- Verify: `composer show | grep -E 'phpstan|cs-fixer|infection'` yields identical versions across all test-bearing packages.
- Exit: a single `just-bases` version bump would upgrade the whole monorepo's QA toolchain.

### Phase 4 — Root orchestration & CI

**4.1** Add root `composer.json` (scripts-only) with a `quality` script that iterates packages, e.g. a small PHP/`find` loop running `composer quality` in each `src/<Pkg>` that defines it.
**4.2** Add `Makefile` at repo root:
```
quality:
	@for d in src/*/; do [ -f "$$d/composer.json" ] || continue; (cd "$$d" && composer quality) || exit 1; done
test cs analyse architecture mutation: quality  # lane aliases
```
**4.3** Add `.github/workflows/quality.yml` running the matrix (one job per package, or `make quality`) on push/PR, failing the build on any lane error.
- Verify: from repo root, `make quality` runs every package's `composer quality` and exits non-zero if any fails.
- Exit: "is the monorepo green?" == one command; CI enforces it.

### Phase 5 — Documentation & reporting

**5.1** Update `docs/REPORT.md §6` divergence table: Test framework → **Codeception (17)** vs PHPUnit (0); Architecture → **phparkitect (17)** vs deptrac (0); Dev baseline → **just-bases (17)**.
**5.2** Update `docs/REPORT.md §8-R2/R3/R4` status (monorepo tooling added; naming standardized; port convergence tracked separately).
**5.3** Add a short "Tooling standard" section to root `EXECUTION.md` and each `AGENTS.md`: *Codeception + phparkitect + just-bases; QA lane names per §6.*
**5.4** Note the consolidation in `CHANGELOG`/commit history per package.
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
                                                        │
                                    Phase 3 (just-bases) ─┤
                                                        │
                                    Phase 4 (root+CI) ────┤
                                                        │
                                    Phase 5 (docs) ───────┘
```

**Do not start Phase 2 batch until Phase 2 pilot dual-run diff is clean.** **Do not delete any `deptrac.archive.yaml` until Phase 5.**

---

## 8. Per-package action matrix

Legend: **CC** = convert tests to Codeception · **PA** = add/port phparkitect (remove deptrac) · **JB** = adopt just-bases baseline.

| Package | Tests today | Arch today | Action |
|---|---|---|---|
| `RunIt` | Codeception | deptrac | PA, JB(already) |
| `CommandIt` | Codeception | deptrac | PA (RunIt-forbid rule), JB(already) |
| `CommitIt` | Codeception | deptrac | PA, JB(already) |
| `ConfigureIt` | Codeception | deptrac | PA, JB(already) |
| `DeriveIt` | Codeception | deptrac | PA, JB(already) |
| `HashIt` | Codeception | deptrac | PA, JB(already) |
| `AsyncIt` | Codeception | deptrac | PA, JB(already) |
| `HashItAsync` | Codeception | deptrac | PA (pilot), JB(already) |
| `DisenchantIt` | Codeception | **phparkitect** | JB (adopt just-bases); serves as template |
| `ConfigureItExplorer` | PHPUnit | none | CC, PA, JB |
| `ConfigureItPsr11` | PHPUnit | none | CC (pilot), PA, JB |
| `ConfigureItSchemaDocs` | PHPUnit | none | CC, PA, JB |
| `ConfigureItSymfony` | PHPUnit | none | CC, PA, JB |
| `ConfigureItTesting` | PHPUnit | none | CC, PA, JB |
| `ConfigureItRunit` | PHPUnit | none | CC, PA, JB |
| `DeriveItRunit` | PHPUnit | none | CC, PA, JB |
| `RunItThirdPartySample` | PHPUnit | none | CC, PA, JB |
| `LazeIt` | none | none | PA (optional), JB (optional) |
| `FileIt` | none | none | PA (optional), JB (optional) |
| `ExtendIt` | none | none | PA (optional), JB (optional) |
| `AdaptIt`/`CodeIt`/`HookIt`/`PatchIt`/`ProjectIt`/`ReflectIt`/`ShowCaseIt`/`WeakenIt` | — | — | No action until they gain code (address `REPORT.md §7-R6`). |

**Totals after execution:** Codeception = 17 (all code-bearing packages) · phparkitect = 17 · just-bases = 17 · deptrac = 0 · PHPUnit as direct dep = 0.

---

## 9. Verification checklist (done when all true)

- [ ] `grep -rl '"phpunit/phpunit"' src/*/composer.json` → empty.
- [ ] `grep -rl 'deptrac' src/*/composer.json` → empty (only `deptrac.archive.yaml` remain).
- [ ] `composer architecture` green in all 17 code-bearing packages.
- [ ] `composer test` green in all test-bearing packages.
- [ ] `make quality` from repo root exits 0.
- [ ] CI `quality.yml` green on a PR touching two packages.
- [ ] `docs/REPORT.md §6` updated; `EXECUTION.md` states the standard.
- [ ] `tools/qa/` holds the shared phparkitect/Codeception/phpstan/cs-fixer/infection configs.
