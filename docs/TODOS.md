# TODOS — Open Tasks Across it-pack

> **Purpose:** Central checklist of all open tasks, prioritized most important first.
> Each task links to the doc(s) and section(s) that describe it.
> Updated: 2026-10-29
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

### 2. Fix impossible PHP constraints (R1) — **COMPLETE**

**Source:** `docs/REPORT.md` §7-R1, §8-R1

All packages now declare `^8.5` as the standard PHP floor (RunIt core at `^8.4`). HashIt/LazeIt/FileIt/ExtendIt all have `php: ^8.5` floors.

- [x] Align Configuration family: Explorer/SchemaDocs/Testing → `^8.5`
- [x] Add `php` constraint to HashIt (`^8.5`)
- [x] Add `php` constraint to LazeIt
- [x] Add `php` constraint to FileIt
- [x] Add `php` constraint to ExtendIt

### 3. Repair or remove the broken root test (R5) — **COMPLETE**

**Source:** `docs/REPORT.md` §7-R5, §8-R5

The root-level `tests/Unit/SDK/CommitItTest.php` that imported non-existent `Tests\Unit\Application/*{}` classes has been removed. The root `tests/` directory is empty.
- [x] Remove the broken root test
- [x] Verify root `tests/` directory is clean

### 4. Expand CI matrix to cover all packages with `quality` scripts

**Source:** `docs/REPORT.md` §7-R12, §8-R12, `docs/MONOREPO_PLAN.md` §5.6

CI matrix now covers all 21 packages (18 code-bearing + 3 micro-libs).

- [x] Standardize `ConfigureIt` scripts (added `quality`, `test`, `security:audit`; renamed `mutation:changed` → `mutation`)
- [x] Standardize `DeriveIt` scripts (added `quality`, `test`, `mutation`, `security:audit`; renamed `infection` → `mutation`)
- [x] Standardize `HashItAsync` scripts (added `quality`, `test`, `mutation`, `security:audit`)
- [x] Add `CommitItRunit` to CI matrix
- [x] Add `ConfigureIt`, `DeriveIt`, `HashItAsync` to CI matrix after standardization
- [x] Add micro-libs (`LazeIt`, `FileIt`, `ExtendIt`) to CI matrix after QA setup (Phase 2.5)

---

## 🟠 Medium priority — will hurt as ecosystem grows

### 5. Schedule port-convergence extraction (R3/R11)

**Source:** `docs/REPORT.md` §7-R3, §8-R4, §5.4

The rule-of-three trigger has fired with **four** bridges
(`AsyncIt`, `ConfigureItRunit`, `DeriveItRunit`, `CommitItRunit`) each reimplementing
RunIt's clock/context/serializer/storage port families. Extract a shared contract package.

- [x] Decide on approach: RunIt's `Contract/Port/*` interfaces are canonical; bridges must implement them directly. Mirror packages (CommandIt/DisenchantIt) retain their own by design (ADR-0001/ADR-0005).
- [x] Create ADR for port convergence (`docs/architecture/decisions/ADR-023-port-convergence-canonical-ports.md`)
- [x] Verify all bridge packages consume RunIt's port interfaces (AsyncIt implements `ContextStorageInterface`, `MonotonicClockInterface`, etc. directly; other bridges use RunIt domain/SDK types)
- [x] Migrate any bridge that defines duplicate port interfaces (verified: AsyncIt implements RunIt ports directly; other bridges consume RunIt types)

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

- [x] Determine if Rector is exercised by the default pipeline — **not** in default pipeline; `RewriterInterface.php` docs describe it as a "future adapter"
- [x] Move `rector/rector` from `require` to `require-dev` in DisenchantIt
- [x] Bump DisenchantIt `php` constraint `^8.4` → `^8.5`

### 8. Standardize QA lane contract across all packages — **COMPLETE**

**Source:** `docs/CONSOLIDATION_PLAN.md` §9 verification checklist, `docs/REPORT.md` §6

All 21 packages (18 code-bearing + 3 micro-libs) now expose the full QA lane: `cs:check`, `cs:fix`, `analyse`, `architecture`, `test`, `test:unit`, `mutation`, `security:audit`, `quality`. Phase 2.5 added QA to LazeIt/FileIt/ExtendIt.

- [x] Add `quality` + `architecture` + standardized scripts to ConfigureIt
- [x] Add `quality` + `architecture` + rename `infection`→`mutation` in DeriveIt
- [x] Add `quality` + `mutation` + `security:audit` to HashItAsync (incl. php `^8.4`→`^8.5`)
- [x] Fix HashIt: php `^8.4`→`^8.5`, add missing `security:audit` script
- [x] Consider adding minimal QA to LazeIt/FileIt/ExtendIt (Phase 2.5)

### 9. Standardize bridge-package naming convention (R3)

**Source:** `docs/REPORT.md` §8-R3, §4.8, §5.3

Built bridges use inconsistent naming. Standardized convention documented in `docs/MONOREPO_PLAN.md` §5.5:

| Category | Convention | Example |
|---|---|---|
| RunIt bridges | `<consumer>-runit` (compact) | `configure-it-runit` |
| Async engine bridges | `<consumer>-async` | `hash-it-async` |
| PSR bridges | `<consumer>-psr11` (no hyphen in PSR) | `configure-it-psr11` |

- [x] Pick and document a single convention (see `docs/MONOREPO_PLAN.md` §5.5)
- [x] Align naming guidance for not-yet-built packages (`command-it-runit`, `disenchant-it-runit`)

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

Assessed all three findings; documented rationale where refactor is deferred:

- [x] **ExtendIt**: `ClassInspector::inspect()` no-op — documented as intentional placeholder with `@param` PHPDoc, code comment, and QA config (`phpstan.neon` ignores `shipmonk.deadMethod`). Ready for consumer implementation.
- [x** **FileIt**: `FileInfo` is anemic — assessed as intentional anemic DTO. Properties typed correctly (`int|false` matches PHP `stat()` return shape). QA infrastructure added to catch type issues. Consider merging into a future "reflection & inspection" package (deferred).
- [x** **DeriveIt**: `ClassReflection` static resolver registry — assessed as lazy-init default cache (private static, immutable after first `??=`, constructor-injected in tests). Documented in class docblock and DeriveIt `AGENTS.md`. Not a service-locator anti-pattern.
- [ ] Consider merging FileIt/ExtendIt/ReflectIt into one "reflection & inspection" package — **deferred**; requires consumer demand and design review.

### 12. Placeholder reserve — add READMEs or delete (R6)

**Source:** `docs/REPORT.md` §7-R6, §8-R6, §4.9

`AdaptIt`, `CodeIt`, `HookIt`, `PatchIt`, `ProjectIt`, `ShowCaseIt`, `WeakenIt` have
zero documentation of intent. `ReflectIt` has a README only.

- [x] Add one-paragraph README stating intent/status/owner to each (AdaptIt, CodeIt, HookIt, PatchIt, ProjectIt, ShowCaseIt, WeakenIt)

### 13. Fix doc-path drift (R10)

**Source:** `docs/REPORT.md` §7-R10

Older docs reference `packages/<Name>/…` paths; the tree uses `src/<Name>/…`.

- [x] Find and fix all `packages/` → `src/` path references in doc files (CODE_REVIEW.md, FINDINGS_HEXAGONAL.md, FINDINGS_SOLID_DRY.md, FINDINGS_DEFECTS.md, REPORT.md)

### 14. Document per-package PSR posture (R11)

**Source:** `docs/REPORT.md` §8-R11

RunIt ships PSR adapters in-tree; CommandIt/ConfigureIt keep PSRs behind owned
ports (bring-your-own via `suggest:`). Document the policy per package.

- [x] Add PSR posture section to root `docs/EXECUTION.md`
- [x] Document in each package's README where relevant (DeriveIt, AsyncIt updated)

### 15. Document tooling standard (Phase 5) — **COMPLETE**

**Source:** `docs/CONSOLIDATION_PLAN.md` §5 Phase 5

- [x] `docs/EXECUTION.md` — Tooling Standard section updated
- [x] `AGENTS.md` — already documents the standard
- [x] `tools/qa/CONVERSION_NOTES.md` — per-package migration log maintained
- [ ] Delete `deptrac.archive.yaml` files after Phase 5 doc sign-off (per CONSOLIDATION_PLAN §5 dual-run rule)
