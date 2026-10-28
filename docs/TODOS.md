# TODOS — Open Tasks Across it-pack

> **Purpose:** Central checklist of all open tasks, prioritized most important first.
> Each task links to the doc(s) and section(s) that describe it.
> Updated: 2026-10-28
>
> **How to use:** Check off tasks when complete. If a task description here
> differs from the source doc, the doc version wins.

---

## 🔴 High priority — blocking / ecosystem risk

### 1. Complete Phase 2.4: Add phparkitect to 9 remaining packages

**Source:** `docs/CONSOLIDATION_PLAN.md` §5 Phase 2.4, `docs/REPORT.md` §7-R12, §5.5, §5.4

Wire `tools/qa/phparkitect/base.php` + `forbidSibling()` into the 9 packages that
still lack architecture verification:

- `ConfigureItExplorer`
- `ConfigureItPsr11`
- `ConfigureItRunit`
- `ConfigureItSchemaDocs`
- `ConfigureItSymfony`
- `ConfigureItTesting`
- `DeriveItRunit`
- `RunItThirdPartySample`
- `CommitItRunit`

**Actions per package:** Create `phparkitect.php` importing `tools/qa/phparkitect/base.php`,
calling `layeredArchitecture()` + appropriate `forbidSibling()` rules, add
`architecture` composer script, dual-run against any archived deptrac config.

- [x] `ConfigureItExplorer` — single-component (Adapter → ConfigureIt)
- [x] `ConfigureItPsr11` — single-component (Adapter → ConfigureIt)
- [x] `ConfigureItRunit` — bridge (Bridge → ConfigureIt + RunIt)
- [x] `ConfigureItSchemaDocs` — single-component (Adapter → ConfigureIt)
- [x] `ConfigureItSymfony` — single-component (Adapter → ConfigureIt)
- [x] `ConfigureItTesting` — single-component (TestDoubles → ConfigureIt)
- [x] `DeriveItRunit` — bridge (Bridge → DeriveIt + RunIt)
- [x] `RunItThirdPartySample` — single-component (Adapter → RunIt)
- [x] `CommitItRunit` — bridge (Bridge → CommitIt + RunIt)

### 2. Fix impossible PHP constraints (R1)

**Source:** `docs/REPORT.md` §7-R1, §8-R1

`configure-it-psr11` and `configure-it-symfony` require `php ^8.5` + `configure-it *@dev`
(requires `^8.5`); Explorer/SchemaDocs/Testing declare `php ^8.3` while depending on
a core requiring `^8.5`. HashIt/LazeIt/FileIt/ExtendIt have no `php` floor.

**Policy:** Cores define the floor (`ConfigureIt` → `^8.5`, `RunIt` → `^8.4`);
satellites inherit `>=` their core's floor; every package declares a `php` constraint.

- [ ] Align Configuration family: Explorer/SchemaDocs/Testing → `^8.5` (or pin to `^8.4` if core is relaxed)
- [ ] Add `php` constraint to HashIt
- [ ] Add `php` constraint to LazeIt
- [ ] Add `php` constraint to FileIt
- [ ] Add `php` constraint to ExtendIt

### 3. Repair or remove the broken root test (R5)

**Source:** `docs/REPORT.md` §7-R5, §8-R5

`tests/Unit/SDK/CommitItTest.php` imports `Tests\Unit\Application\{FakeFactory, FakeRepository, RecordingObserver}`
which exist in `src/CommitIt/tests/Unit/Application/` but are not autoloaded from the root
`tests/` directory.

- [ ] Give the root test its own fakes directory + autoload config, or remove it
- [ ] Add root-level test CI lane if kept

### 4. Expand CI matrix to cover all packages with `quality` scripts

**Source:** `docs/REPORT.md` §7-R12, §8-R12, `docs/MONOREPO_PLAN.md` §5.6

`.github/workflows/quality.yml` currently covers 14 packages. Missing:
`ConfigureIt` (no `quality` script), `DeriveIt` (uses `infection` not `mutation`),
`HashItAsync` (no `quality` script), `CommitItRunit` (has `quality` but not in matrix).

- [ ] Standardize `ConfigureIt` scripts (add `quality`, `test`, `security:audit`; rename `mutation:changed` → `mutation` if appropriate)
- [ ] Standardize `DeriveIt` scripts (add `quality`, `test`, `mutation`, `security:audit`; rename `infection` → `mutation`)
- [ ] Standardize `HashItAsync` scripts (add `quality`, `test`, `mutation`, `security:audit`)
- [ ] Add `CommitItRunit` to CI matrix
- [ ] Add `ConfigureIt`, `DeriveIt`, `HashItAsync` to CI matrix after standardization

---

## 🟠 Medium priority — will hurt as ecosystem grows

### 5. Schedule port-convergence extraction (R3/R11)

**Source:** `docs/REPORT.md` §7-R3, §8-R4, §5.4

The rule-of-three trigger has fired with **four** bridges
(`AsyncIt`, `ConfigureItRunit`, `DeriveItRunit`, `CommitItRunit`) each reimplementing
RunIt's clock/context/serializer/storage port families. Extract a shared contract package.

- [ ] Decide on approach: `it-pack/port-it` shared-contract package, or RunIt promotes `Contract/Port` to canonical re-exported definitions
- [ ] Create ADR for port convergence
- [ ] Extract shared interfaces
- [ ] Migrate bridges to use shared interfaces
- [ ] Record occurrence-name registries centrally under RunIt's `vocab/`

### 6. Add Monorepo Builder (Monorepo Plan)

**Source:** `docs/MONOREPO_PLAN.md` §3–§4

Root orchestration (Phase 4) is done, but independent Packagist publishing via
`symplify/monorepo-builder` is not yet implemented.

- [ ] Decide: rename `src/` → `packages/`, or use `monorepo-builder.json` merge paths pointing at `src/*`
- [ ] Rename `src/` → `packages/` with full reference update (if chosen)
- [ ] Add `monorepo-builder.json` configuration
- [ ] Add `symplify/monorepo-builder:^12.0` to root `require-dev`
- [ ] Verify `monorepo-builder merge` works with path repos still in place
- [ ] Create `.github/workflows/release.yml`
- [ ] Update `AGENTS.md` with split/release workflow
- [ ] Evaluate `monorepo-builder` vs `monorepo-tool`/subtree-split before first Packagist tag

### 7. Move `rector/rector` out of DisenchantIt's runtime requires (R8)

**Source:** `docs/REPORT.md` §7-R8, §8-R8

`DisenchantIt` carries `rector/rector ^2.0` as a runtime dependency while its own
docs say rewriting is pluggable and Rector is an anticipated *alternative* engine.

- [ ] Determine if Rector is exercised by the default pipeline
- [ ] If not: move to `require-dev` or `suggest`

### 8. Standardize QA lane contract across all packages

**Source:** `docs/CONSOLIDATION_PLAN.md` §9 verification checklist, `docs/REPORT.md` §6

6 packages lack `quality` (LazeIt, FileIt, ExtendIt remain micro-libs → Phase 2.5).
2 use `infection` instead of `mutation` — RESOLVED (DeriveIt renamed). 9 lack `architecture` — RESOLVED (Phase 2.4).
HashIt missing `security:audit` script — RESOLVED.

- [x] Add `quality` + `architecture` + standardized scripts to ConfigureIt
- [x] Add `quality` + `architecture` + rename `infection`→`mutation` in DeriveIt
- [x] Add `quality` + `mutation` + `security:audit` to HashItAsync (incl. php `^8.4`→`^8.5`)
- [x] Fix HashIt: php `^8.4`→`^8.5`, add missing `security:audit` script
- [ ] Consider adding minimal QA to LazeIt/FileIt/ExtendIt (Phase 2.5)

### 9. Standardize bridge-package naming (R3)

**Source:** `docs/REPORT.md` §8-R3, §4.8, §5.3

Built bridges use inconsistent naming:
`<consumer>-run-it` suffix (`configure-it-runit`, `derive-it-runit`) vs
full-word (`commit-it-runit`, `run-it-third-party-sample`). Still-unbuilt use
`command-it-run-it` / `run-it-disenchant-it`.

- [ ] Pick and document a single convention
- [ ] Align naming of not-yet-built packages before publication

---

## 🟡 Low priority — hygiene

### 10. Complete metadata sweep (R9)

**Source:** `docs/REPORT.md` §7-R9, §8-R9, `docs/MONOREPO_PLAN.md` §2.4

| Package | Missing | Status |
|---|---|---|
| HashIt | `php` floor | ✅ Resolved (`^8.5`) |
| LazeIt | `php` floor, `license`, `description` | ✅ Already present |
| FileIt | `php` floor, `license`, `description` | ✅ `php` added; description improved |
| ExtendIt | `php` floor, `license`, `description` | ✅ `php` added; `license` + `description` added |
| DeriveIt | `description` | ✅ Added |

- [x] Add `php` floor to HashIt (→ `^8.5`)
- [x] Add `php` floor + `license` + `description` to LazeIt (already present)
- [x] Add `php` floor + `license` + `description` to FileIt (`php` added, description improved)
- [x] Add `php` floor + `license` + `description` to ExtendIt (all added)
- [x] Add `license` to DeriveIt (already present)
- [x] Improve FileIt description ("ItPack File Package" → meaningful text)
- [ ] Align `ConfigureItPsr11` naming (`-psr11` vs `-psr-11`) — needs naming decision

### 11. Close out legacy findings (R7)

**Source:** `docs/REPORT.md` §7-R7, §8-R7

- [ ] ExtendIt: `ClassInspector::inspect()` is a no-op — implement or shelve with notes
- [ ] FileIt: `FileInfo` is anemic (9 untyped public properties, `int|false` lies) — fix type mismatch and implement behavior
- [ ] DeriveIt: `ClassReflection` doubles as a static resolver registry (service-locator smell) — refactor
- [ ] Consider merging FileIt/ExtendIt/ReflectIt into one "reflection & inspection" package

### 12. Placeholder reserve — add READMEs or delete (R6)

**Source:** `docs/REPORT.md` §7-R6, §8-R6, §4.9

`AdaptIt`, `CodeIt`, `HookIt`, `PatchIt`, `ProjectIt`, `ShowCaseIt`, `WeakenIt` have
zero documentation of intent. `ReflectIt` has a README only.

- [ ] Add one-paragraph README stating intent/status/owner to each, or delete the directory

### 13. Fix doc-path drift (R10)

**Source:** `docs/REPORT.md` §7-R10

Older docs reference `packages/<Name>/…` paths; the tree uses `src/<Name>/…`.

- [ ] Find and fix all `packages/` → `src/` path references in doc files

### 14. Document per-package PSR posture (R11)

**Source:** `docs/REPORT.md` §8-R11

RunIt ships PSR adapters in-tree; CommandIt/ConfigureIt keep PSRs behind owned
ports (bring-your-own via `suggest:`). Document the policy per package.

- [ ] Add PSR posture section to root `docs/EXECUTION.md`
- [ ] Document in each package's README where relevant

### 15. Document tooling standard (Phase 5)

**Source:** `docs/CONSOLIDATION_PLAN.md` §5 Phase 5

- [x] `docs/EXECUTION.md` — Tooling Standard section updated
- [x] `AGENTS.md` — already documents the standard
- [x] `tools/qa/CONVERSION_NOTES.md` — per-package migration log maintained
- [ ] Delete `deptrac.archive.yaml` files after Phase 5 doc sign-off (per CONSOLIDATION_PLAN §5 dual-run rule)
