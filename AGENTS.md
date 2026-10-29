# AGENTS.md

## Purpose

This file guides AI coding agents working at the **it-pack monorepo** root
(`it-pack/`). It defines the repository-wide tooling and QA standard. Package-local
concerns live in each `src/<Pkg>/AGENTS.md` (e.g. `src/RunIt/AGENTS.md`,
`src/DisenchantIt/AGENTS.md`); when they conflict with this file, the
package-local file wins for that package's internals, but the consolidated
tooling standard below is the monorepo default.

Use these instructions when scaffolding, migrating, reviewing, or documenting
packages, and when deciding which test framework / architecture verifier / dev
baseline a package should use.

---

## Instruction Priority

1. The current user or task request.
2. Repository-local instructions in the nearest `AGENTS.md`.
3. `docs/CONSOLIDATION_PLAN.md` (the authoritative migration plan).
4. `docs/EXECUTION.md` (the shared execution model + tooling standard).
5. Package-local `AGENTS.md` and `composer.json`.
6. Existing code conventions.

---

## Monorepo Layout

```text
it-pack/
├── docs/                 # REPORT.md, EXECUTION.md, CONSOLIDATION_PLAN.md, ADRs…
├── src/<Pkg>/            # one composer package per directory (independent repos
│                         #   when published; monorepo here for development)
├── tools/qa/             # SHARED QA templates (Codeception/phpstan/cs-fixer/
│                         #   infection/phparkitect) — the consolidation anchor
├── .github/workflows/    # quality.yml etc.
└── examples/ lab/ var/   # tinkering / samples (not shipped)
```

Each `src/<Pkg>/` is an independent package with its own `composer.json`,
`tests/`, and QA config. The **root** `composer.json` is scripts-only and
delegates QA to per-package scripts via the `Makefile` (`make quality`). See
`docs/CONSOLIDATION_PLAN.md` Phase 4 for details.

---

## Consolidated Tooling Standard (mandated)

The maintainer mandated a single stack. New code and migrations follow it:

| Concern | Tool | Notes |
|---|---|---|
| Tests | **Codeception** | `Unit` suite subclasses `PHPUnit\Framework\TestCase`; PHPUnit is not a first-class direct dependency. |
| Architecture | **phparkitect** | Replaces deptrac. deptrac configs were archived as `deptrac.archive.yaml` during Phase 2.4, then deleted after Phase 5 documentation was complete. |
| Dev baseline | **just-bases/\*** | `php-unit-test-base`, `php-qa-base`, `php-obs-dev-base` for phpstan / cs-fixer / infection pins. |
| Shared config | `tools/qa/` | Copy or include the canonical templates; do not re-pin versions ad-hoc. |

### QA lane contract

Every package with source code exposes these composer scripts:

| Script | Command |
|---|---|
| `test` | `vendor/bin/codecept run` |
| `test:unit` | `vendor/bin/codecept run Unit` |
| `cs:check` | `vendor/bin/php-cs-fixer fix --dry-run --diff` |
| `cs:fix` | `vendor/bin/php-cs-fixer fix` |
| `analyse` | `vendor/bin/phpstan analyse` |
| `architecture` | `vendor/bin/phparkitect` |
| `mutation` | `vendor/bin/infection --min-msi=<pkg-appropriate>` |
| `quality` | `cs:check` → `analyse` → `architecture` → `test` → `mutation` → `security:audit` |

### Architecture rules

- Hexagonal layer contract: `Contract → nothing internal`; `Core/Domain →
  Contract, ValueObject`; `Adapter → Contract (+ vendor)`, never `Core`.
- **Ports, not imports:** a consumer's `Core` must **not** import
  `ItPack\RunIt\*`. RunIt is the base execution layer and is wired in through a
  bridge package (`it-pack/run-it-<consumer>` or `<consumer>-run-it`). Enforced
  via the shared `forbidSibling()` helper.
- Do not add skips / baselines to hide an architectural violation. Correct the
  dependency direction; if a boundary must change, update the architecture and
  the ADR first.

---

## Shared QA Templates (`tools/qa/`)

| File | Use it for |
|---|---|
| `phparkitect/base.php` | `layeredArchitecture()` + `classSet()` helpers (namespace `ItPack\Qa\PhpArkitect`). |
| `phparkitect/forbid-sibling.php` | `forbidSibling()` — "ports-not-imports" rule generator. |
| `codeception.dist.yml` | Copy to a package's `codeception.yml`. |
| `phpstan.neon.dist` | Base PHPStan config (`level: max`). |
| `.php-cs-fixer.dist.php` | Base PHP-CS-Fixer rule set. |
| `infection.json5` | Base Infection config (Codeception adapter). |
| `CONVERSION_NOTES.md` | Pilot recipes + per-package migration log. |

A package's `phparkitect.php` consumes the shared base by
`require_once __DIR__ . '/../../tools/qa/phparkitect/base.php';` (adjust the
relative path to the repo root) and calling `layeredArchitecture()` /
`forbidSibling()`. `src/DisenchantIt/phparkitect.php` is the reference consumer.

---

## Workflow Discipline

- Prefer modifying existing focused components over creating parallel
  abstractions.
- Keep patches small and reviewable; one coherent change at a time.
- Run the narrowest relevant checks before broader ones; run `composer quality`
  in the package under change.
- When migrating a package, follow the pilot-then-batch sequence in
  `docs/CONSOLIDATION_PLAN.md`; record the migration in
  `tools/qa/CONVERSION_NOTES.md`.
- Never disable architecture rules or regenerate baselines to make CI pass.
- Do not commit generated artifacts unless the repository requires them.

---

## Monorepo Tooling & Release Workflow

The repo uses **`symplify/monorepo-builder`** for release/split automation.
Configuration lives in the root `monorepo-builder.php` (PHP format, consumed
by the Merge/Split commands). The root `composer.json` is scripts-only and
includes `symplix/monorepo-builder:^12.0` in `require-dev`.

### Development workflow (path repos)

During development, packages reference each other via **symlinked path
repositories** (`"type": "path"`, `"options": { "symlink": true }` in
`src/<Pkg>/composer.json`). This gives live cross-package editing. CI runs
`composer quality` per package in `src/<Pkg>/`.

### Release workflow (monorepo-builder)

1. **Tag-based trigger**: push a tag matching `v*` to trigger
   `.github/workflows/release.yml`.
2. **Quality gate**: the release workflow runs `composer quality` across all
   21 packages before attempting a release.
3. **Merge**: `monorepo-builder merge --config=monorepo-builder.php` compiles
   all `src/<Pkg>/composer.json` files into a single root `composer.json`,
   injecting `replace` entries for every sibling package so the merged tree
   resolves all internal deps from one manifest.
4. **Version conflicts**: packages may declare different version constraints
   for the same transitive dependency (e.g., `codeception/codeception` `^3.0`
   vs `^4.1`). These are listed in `EXCLUDE_PACKAGE_VERSION_CONFLICTS` in
   `monorepo-builder.php` to allow the merge to proceed; the merged root
   uses the highest compatible constraint.
5. **Release**: `monorepo-builder release <version> --config=monorepo-builder.php`
   tags and publishes all packages at the given version.
6. **Post-split**: after release, each package can be independently published
   to Packagist via git subtree splits.

### Directory layout

Packages live in `src/<Pkg>/` (kept as-is; no `src/` → `packages/` rename).
The `monorepo-builder.php` config uses relative paths from the repo root.

---

## Completion Report

When returning a completed task, report concisely:

- What changed and why.
- Tests and quality checks run (and any not run, with reasons).
- Public API / backward-compatibility impact.
- Remaining risks or follow-up work.
