# Monorepo Consolidation Plan — Monorepo Builder Integration

> **Status:** Phase 4 (root orchestration) complete; Monorepo Builder not yet implemented — this document details the remaining work: integrating `symplify/monorepo-builder` to enable independent Packagist packages from the monorepo.
> **Context:** `docs/CONSOLIDATION_PLAN.md` Phase 4 added root orchestration (`composer.json` + `Makefile` + CI matrix). This document details the next step: integrating `monorepo-builder` to enable independent Packagist packages from the monorepo.

---

## 1. Why Monorepo Builder

Packagist publishes packages at the **repository root level** — each package
must be its own top-level directory with its own `composer.json`. Today,
it-pack packages live in `src/<Pkg>/` and are wired together via **path
repositories** (`../ConfigureIt`, `../RunIt`, etc.). This works for local
development but:

- Prevents independent Packagist tags
- Multiplies `vendor/` trees (18×)
- Has no release/split automation

`monorepo-builder` solves this by:

1. **`merge`** — copies each `src/<Pkg>/*/` into a split tree at `packages/<pkg-name>/`
   with a synthesized root-level `composer.json`
2. **`split`** — creates git subtrees for each merged package, publishable to Packagist
3. **`bump`** — coordinated version bumps across all packages
4. **`release`** — merge + test + tag + split as one command

It complements (not replaces) the existing stack: Codeception, phparkitect,
PHPStan, PHP-CS-Fixer, and Infection continue to run per-package as-is.

---

## 2. Current State

### 2.1 Wiring model

All 18 code-bearing packages use **path repositories** in their `composer.json`:

```json
"repositories": [
    { "type": "path", "url": "../ConfigureIt", "options": { "symlink": true } }
]
```

This symlink-based approach means each `vendor/` tree contains a symlink to
the sibling package, not a copy. It works locally but is not Packagist-ready.

**Note:** Root orchestration (Phase 4 of `CONSOLIDATION_PLAN.md`) is already in place — `composer.json` (scripts-only), `Makefile` (`make quality`), and `.github/workflows/quality.yml` (14×2 matrix). This enables monorepo-wide QA but does **not** provide independent Packagist publishing; Monorepo Builder is still needed for that.

### 2.2 Package inventory

| Category | Count | Packages |
|---|---|---|
| Code-bearing (has `composer.json` + `src/`) | 18 | RunIt, ConfigureIt + 5 satellites, CommandIt, CommitIt, DisenchantIt, DeriveIt, HashIt, AsyncIt, HashItAsync, DeriveItRunit, ConfigureItRunit, RunItThirdPartySample, **CommitItRunit** |
| Test-bearing (has `tests/`) | 15 | All above except: LazeIt, FileIt, ExtendIt |
| Micro-libs (minimal config) | 3 | LazeIt (1 class, no tests), FileIt (1 class), ExtendIt (stub) |
| Placeholders (no `composer.json`) | 8 | AdaptIt, CodeIt, HookIt, PatchIt, ProjectIt, ReflectIt, ShowCaseIt, WeakenIt |

### 2.3 QA state

- **Phase 0–4 of `CONSOLIDATION_PLAN.md` largely complete.** All 18 code-bearing packages use Codeception (via `just-bases` or direct require); 9 have phparkitect (Phase 2 incomplete — 9 packages still lack it); deptrac fully archived; 15 packages have `quality` scripts (ConfigureIt, DeriveIt, HashItAsync lack it; 3 micro-libs have none).
- **CI matrix coverage:** `.github/workflows/quality.yml` runs 14 packages × PHP 8.4/8.5. Missing from CI: ConfigureIt (no `quality` script), DeriveIt (uses `infection` not `mutation`), HashItAsync (no `quality` script), CommitItRunit (has `quality` but not yet added to matrix).

### 2.4 Metadata gaps (R9)

| Package | Missing |
|---|---|
| HashIt, LazeIt, FileIt, ExtendIt | `php` floor not declared |
| DeriveIt | `license` field not declared |
| LazeIt, FileIt, ExtendIt | Description/license incomplete |

---

## 3. Proposed Split Strategy

### 3.1 Directory layout

Rename `src/` → `packages/` to match Monorepo Builder convention and avoid
confusion with each package's internal `src/` directory:

```text
it-pack/
├── composer.json          # Root (scripts-only)
├── Makefile
├── monorepo-builder.json  # Monorepo Builder config
├── packages/              # Renamed from src/
│   ├── run-it/
│   ├── configure-it/
│   ├── command-it/
│   ├── ... (all 18 packages)
│   ├── laze-it/
│   ├── file-it/
│   └── extend-it/
├── merge/                 # Monorepo Builder output (gitignored)
├── docs/
├── tools/qa/
└── .github/workflows/
```

**Naming convention**: Monorepo Builder converts package names to kebab-case
(`it-pack/run-it` → `run-it`). This aligns with Packagist requirements.

### 3.2 Split vs. merge

- **`merge`** (`monorepo-builder merge`): Compiles all packages into a
  single `merge/` directory for testing the monorepo as a whole. Useful for
  CI matrix — run tests in the merged tree to verify cross-package edges.
- **`split`** (`monorepo-builder split`): Creates git subtrees at the root
  level matching Packagist package names. Each split subtree is independently
  publishable.

**Recommendation**: Use split subtrees for Packagist, merge for monorepo
integration testing. This is the standard Monorepo Builder workflow.

---

## 4. Step-by-Step Implementation Plan

### Phase A — Configuration setup

#### A.1 Rename `src/` → `packages/`

```bash
git mv src packages
```

Update all references:
- `composer.json` scripts in each package (test paths, coverage paths)
- `phpstan.neon` files (paths)
- `codeception.yml` files (paths)
- `.php-cs-fixer.php` files (finder paths)
- CI workflow paths
- Documentation references (`docs/REPORT.md`, `docs/EXECUTION.md`)
- `AGENTS.md` references

**Risk**: High churn — touches every package. Should be the first step so all
other paths resolve correctly.

#### A.2 Complete metadata sweep (R9)

Before adding Monorepo Builder, complete the metadata gaps identified in
§2.4:

- Add `php` constraint to HashIt, LazeIt, FileIt, ExtendIt
- Add `license` field to DeriveIt
- Add/improve descriptions where placeholder text exists

#### A.3 Add Monorepo Builder to root

```json
// composer.json (root, scripts-only → add require-dev)
{
    "require-dev": {
        "symplify/monorepo-builder": "^12.0"
    }
}
```

#### A.4 Create `monorepo-builder.json`

```json
{
    "merge": {
        "packages": [
            "packages/*"
        ],
        "exclude_guides": [
            ".git",
            ".github",
            "docs/",
            "tools/qa/",
            "examples/",
            "lab/",
            "var/"
        ]
    },
    "split": {
        "packages": {
            "run-it": "packages/run-it",
            "configure-it": "packages/configure-it",
            "configure-it-psr11": "packages/configure-it-psr11",
            ...
        }
    }
}
```

### Phase B — Infrastructure migration

#### B.1 Convert path repositories to Monorepo Builder includes

Remove all `repositories` path-repo entries from individual package `composer.json`
files. Replace with Monorepo Builder's `include` mechanism during merge.

Example transformation:
```json
// Before (in ConfigureItPsr11/composer.json)
"repositories": [
    { "type": "path", "url": "../ConfigureIt", "options": { "symlink": true } }
]

// After: Monorepo Builder handles this during merge
```

In the `monorepo-builder.json`, use `replace` or `provide` to wire packages:
```json
"merge": {
    "packages": ["packages/*"],
    "replace": {
        "it-pack/configure-it": "self.version"
    }
}
```

This means during merge, all sibling packages are automatically available
without explicit path repos.

#### B.2 Update CI to use merge for monorepo testing

Add a CI job that runs `monorepo-builder merge` then runs tests in the merged
tree. This verifies that all cross-package edges work correctly.

#### B.3 Add release workflow

Create `.github/workflows/release.yml`:
```yaml
name: Release
on:
  push:
    tags: [ 'v*' ]

jobs:
  release:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - run: composer install
      - run: vendor/bin/monorepo-builder release ${{ github.ref_name }}
```

### Phase C — Release automation

#### C.1 Version synchronization

Use `monorepo-builder bump` to coordinate version bumps across all packages.
This addresses REPORT.md §7-R4 (release-coordination hazard).

#### C.2 Individual package tagging

After `monorepo-builder split`, each package gets its own git subtree tag
that can be published to Packagist independently.

---

## 5. Key Considerations & Risks

### 5.1 Path repository conversion

The most complex part. Each `src/*/composer.json` has path-repo references.
Monorepo Builder handles this via merge-time `replace` directives, but requires
removing the path repos from individual manifests.

**Mitigation**: Do this in two commits — first add `monorepo-builder.json`
with all packages, verify merge works, then remove path repos from individual
manifests.

### 5.2 Symlink-based development

The current symlink path repos (`symlink: true`) mean developers have live
edits across packages. The merge tree uses copies, which breaks live editing.

**Mitigation**: Keep path repos for development (with symlinks), use
Monorepo Builder only for release/merge CI. This is the standard approach:
- **Development**: path repos (fast, symlinks)
- **CI release**: Monorepo Builder merge (isolated, reproducible)

### 5.3 PHP version floors (R1, R9)

Packages declare inconsistent PHP floors (`^8.3`, `^8.4`, `^8.5`).
Monorepo Builder can enforce a root constraint:

```json
{
    "merge": {
        "php": "^8.4"
    }
}
```

**Recommendation**: Standardize on `^8.4` (the lowest common denominator
among cores). Align satellite floors before implementing.

### 5.4 Metadata gaps

Monorepo Builder validates composer.json — missing `php` floors, `license`
fields, etc. will fail merge. Complete the §2.4 metadata sweep first.

### 5.5 Package naming

Current package names use various conventions:
- `it-pack/configure-it-psr11` (hyphenated PSR-11)
- `it-pack/run-it-third-party-sample`

Monorepo Builder's split uses the package `name` from each `composer.json`,
so split directory names will match Packagist. No renaming needed beyond
the `src/` → `packages/` move.

### 5.6 CI matrix impact

The current CI matrix runs 14 packages × PHP 8.4/8.5 on push/PR (see `.github/workflows/quality.yml`). After migration:
- **Development CI** (push/PR): Currently runs `make quality` for 14 packages. Should be expanded to 15 once ConfigureIt/DeriveIt/HashItAsync/CommitItRunit standardize their scripts.
- **Merge CI** (pre-release): Run tests in the merged tree to verify cross-package edges
- **Release CI** (tag): Run `monorepo-builder release`

Before adding Monorepo Builder, standardize the 4 outlier packages' QA scripts so the CI matrix can cover all 15+ packages uniformly.

### 5.7 Not affected by this change

- Per-package QA configs (`phpstan.neon`, `.php-cs-fixer.php`, `infection.json5`,
  `codeception.yml`) — these are relative to each package and don't change
- Per-package `phparkitect.php` — same
- `tools/qa/` shared templates — same
- `AGENTS.md` guidance — path references need updating

---

## 6. Alternative approaches considered

### 6.1 Composer workspaces (RFC)

PHP Composer has experimental workspace support that could replace path repos.
However, it's not yet stable and doesn't handle the split-to-Packagist
workflow. Monorepo Builder is production-ready.

### 6.2 git subtree / git subrepo

Could manually split packages using git subtrees, but Monorepo Builder
automates the directory mapping, version coordination, and split tagging.
It also handles the merge step for integration testing.

### 6.3 Composer merge plugin

`wikimedia/composer-merge-plugin` could handle the merge side but has no
split/release capabilities. More limited than Monorepo Builder.

---

## 7. Next steps

**Prerequisite (consolidation):**
1. **Standardize QA scripts** in ConfigureIt, DeriveIt, HashItAsync (add `quality`; DeriveIt uses `infection` not `mutation`).
2. **Complete Phase 2.4** (add phparkitect to 9 remaining packages) — see `CONSOLIDATION_PLAN.md` §5 Phase 2.4.
3. **Add CommitItRunit** to the CI matrix.

**Monorepo Builder work (this document):**
1. ✅ **Completed: root `composer.json` (scripts-only)** — Phase 4 of CONSOLIDATION_PLAN.
2. ✅ **Completed: `Makefile`** at repo root with `make quality`.
3. ✅ **Completed: `.github/workflows/quality.yml`** — 14×2 matrix CI.
4. Complete metadata sweep (R9) — add `php` floors to HashIt, LazeIt, FileIt, ExtendIt; add `license` to DeriveIt.
5. **Standardize PHP floors** (align on `^8.4` or `^8.5`) — see REPORT.md §7-R1.
6. **Rename `src/` → `packages/`** with full reference update (or keep `src/` and use `monorepo-builder.json` merge paths pointing at `src/*`).
7. **Add `monorepo-builder.json`** + require-dev `symplify/monorepo-builder:^12.0`.
8. **Verify merge** works with path repos still in place.
9. **Create release CI** workflow (`release.yml`).
10. **Update AGENTS.md** with split/release workflow.
11. **Document in `docs/REPORT.md`** §7-R2 (monorepo tooling) and §8-R2 (recommendation 2).
