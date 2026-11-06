# RunIt Agent Guide

This guide applies to `src/RunIt/` in the ItPack monorepo. Paths and commands
below are relative to this package unless stated otherwise. Follow the current
task and higher-priority instructions first; use the monorepo root `AGENTS.md`
for shared requirements and this file for RunIt-specific guidance.

## Start Here

1. Inspect the worktree and preserve unrelated changes. Do not commit, tag,
   push, or publish unless explicitly requested.
2. Read only the affected contracts, implementation, tests, and documentation.
   For planned refactoring, check `docs/REFACTORING_PLAN.md` and its dependencies
   and decisions. Its checked decisions describe intended policies, not proof
   that those policies are implemented.
3. Make the smallest coherent change. Add focused tests for behavior changes and
   update affected documentation. Do not silently change public contracts,
   security defaults, domain semantics, or serialized formats.
4. Run the narrowest relevant checks, then broader checks when appropriate.
   Report failures, skipped checks, unavailable extensions, and compatibility
   impact accurately. Do not claim a historical result is a current result.

If a material API, security, lifecycle, or compatibility choice is not already
authorized, ask for a decision rather than guessing. A review request is
advisory; do not modify files unless fixes are explicitly requested.

## Package And Release

- Composer package: `it-pack/run-it`; PHP constraint: `^8.4` in `composer.json`.
- The current RunIt version is **1.1.2**, as provided by the maintainer. The
  package's split repository is <https://github.com/it-pack/run-it>.
- Development takes place in this monorepo; packages are split to separate
  repositories using git split. Monorepo tags do not automatically become
  package-repository tags. Each package must be tagged separately when a release
  requires it. Do not infer a package's released version or tag status from
  monorepo tags alone.
- The split mapping is in `../../.gitsplit.yml`; the split workflow is
  `../../.github/workflows/split-monorepo.yaml`. Check these and the actual
  release workflow rather than assuming monorepo-builder performs the split.
- Before release work, verify the intended repository, split revision, package
  version, remote tags, and current release procedure. A monorepo workflow or
  historical release document alone does not prove a package was published.
  Do not create tags, push, split, or publish without explicit authorization.
- `docs/releases/1.0.0-rc.1.md` is a historical release record, not the current
  package version. Check current package metadata and the split repository when
  release details matter.

## Find The Right Context

| Need | Start with |
| --- | --- |
| Package setup and public usage | `README.md`, `composer.json` |
| Execution model | `docs/EXECUTION.md`, relevant `docs/architecture/` and `docs/adr/` |
| Current refactoring work | `docs/REFACTORING_PLAN.md`, `docs/REVIEW.md` |
| Public interfaces and schemas | `src/Contract/`, `config/normalization-schema.json`, `docs/compatibility.md` |
| Architecture and analysis rules | `phparkitect.php`, `phpstan.neon`, relevant `docs/QA.md` sections |
| Compatibility checker | `tools/api-compat/config.json`, `tools/api-compat/` |
| Domain vocabulary | `vocab/run-it.ttl`, `vocab/run-it-taxonomy.ttl` |

Some older documents describe historical results, RC status, or Deptrac. Check
the current source, configuration, and command output before relying on them.
Do not invent the contents of missing documents or assume a historical test
count, coverage percentage, or analysis result is still valid.

## Architecture And Domain

RunIt models an execution and its context, activities, measurements,
observations, occurrences, and snapshots. Use RunIt terminology in core code;
OpenTelemetry, CloudEvents, PSR, and coroutine runtimes are integrations, not
the definition of domain semantics. Keep optional integrations optional.

- Follow the package's actual phparkitect rules in `phparkitect.php`. Domain
  and application code must not import concrete external adapters or depend on
  frameworks or vendor telemetry implementations. Keep external effects behind
  ports. The executable rules do not necessarily enforce every boundary in the
  architecture prose; inspect both when changing boundaries.
- Keep artifacts and context immutable where their contracts require it. An
  execution has at most one terminal outcome; preserve the transition rules in
  `ExecutionStatus` and activity lifecycle contracts. Use explicit identity
  and relationships for parentage; do not invent implicit ownership.
- Use monotonic time for durations and required clock dependencies where
  components need them. Preserve execution-before-snapshot publication order.
- Restore prior context even on failure. The SDK's default stack is for
  synchronous nesting, not isolation between interleaved Fibers or coroutines;
  configure runtime-aware storage explicitly for concurrent use.
- Preserve the business result and the original business exception when
  telemetry, cleanup, or adapters fail. Apply the configured failure policy and
  prevent recursive publication; do not silently swallow failures.
- Treat context, throwable data, observations, occurrences, and snapshots as
  potential secret-exposure paths. Bound payloads, recursion, buffering, and
  cardinality; avoid arbitrary object serialization. Add negative tests for
  security-sensitive paths. The plan records approved redaction defaults and
  ordering decisions, but verify actual implementation before claiming them.
- Do not use `ContextTokenInterface::id()` alone to establish ownership or token
  equality: its stable integer is storage-defined and need not be unique.
- Benchmark changes to hot paths, publication, serialization, buffering, or
  context storage; do not claim a performance improvement without measurements.

## PHP And Design

- Use `declare(strict_types=1)` in PHP files. Follow existing PHPStan and
  PHP-CS-Fixer configuration; use native types and PHPDoc generics where needed.
- Add missing function docstrings where required by the docstring-coverage
  pre-merge threshold; do not claim the check passed without running it.
- Prefer final classes, readonly values, composition, and small interfaces at
  genuine boundaries. Follow documented project rule exemptions; do not create
  factories or interfaces mechanically where they add no useful seam.
- Keep integration-specific mapping in adapters. Avoid global mutable execution
  state, hidden service locators, unnecessary dependencies, and speculative
  abstractions. Use modern PHP features when they make the code clearer.
- Preserve framework-neutral conformance tooling so adapter authors do not need
  to inherit a specific test-framework base class.
- When changing public domain concepts or serialized vocabulary, consult the
  vocabulary files and relevant ADRs; update corresponding terms and adjacent
  references when necessary. Do not add RDF as a runtime dependency.

## Tests And Quality

Inspect `composer.json` first: it is the source of truth for available scripts.
Agents may run `composer update` in RunIt to test against current dependency
versions allowed by its constraints. The package's `composer.lock` is ignored;
do not commit it or change constraints merely to make an update pass. Preserve
pre-existing changes and report dependency-resolution and subsequent test failures.
Examples below run from `src/RunIt/` with package dependencies installed:

```bash
composer validate --strict
composer test:unit
composer test:integration
composer test:conformance
composer test:concurrency
composer test
composer coverage
composer cs:check
composer analyse
composer architecture
composer lint:length
composer api:check
composer security:audit
composer quality
```

- `composer test` runs Unit, Integration, Conformance, and Concurrency.
  `composer coverage` measures **Unit only**. Do not merge other suites into
  Unit coverage. An aggregate percentage does not prove per-test attribution.
  Include an explicit class under test and allow justified real collaborators
  and documented exceptions.
- Use the relevant Unit tests for focused behavior, Integration for cross-layer
  wiring, Conformance for port behavior, and Concurrency for isolation. Include
  failure paths and extension-specific tests when relevant; report skips.
- `composer quality` runs validation, style, analysis, architecture, line
  length, tests, and security audit. It does **not** run coverage, API checks,
  mutation tests, or benchmarks; run those separately when relevant. Other
  available scripts include `composer mutation`, `composer benchmark`, and
  `composer examples`.
- phparkitect is the architecture gate, not Deptrac. Do not disable rules or
  regenerate baselines to hide a failure. When a check fails, report the exact
  result and distinguish existing issues from changes introduced by the task.
- Generated test support and coverage reports may change when tests run. Do not
  commit generated artifacts unless the repository requires them, and never
  discard changes that predate your work.

## Public API And Compatibility

RunIt is a library with independently released consumers. Public signatures,
interfaces, normalized fields, serialized schemas, exception behavior, and
adapter extension contracts are compatibility-sensitive. Adding an interface
method can break implementations even if it is additive for callers. Assess
changes against the actual released package, not just the monorepo version.

`tools/api-compat/config.json` defines the checker's public directories and
internal namespace exclusions. `composer api:check` compares the API and
normalization schema with their baselines; it can exit nonzero for additions as
well as breaks, and it is not an exhaustive compatibility proof. Inspect its
full output and verify baselines exist.

Never run `composer api:baseline` merely to make a check pass. It regenerates
the full API baseline and, when present, the normalization-schema baseline. Run
it only for an explicitly approved baseline update, inspect **both** resulting
diffs for unrelated changes, document the compatibility and migration impact in
`docs/CHANGELOG.md`, and rerun `composer api:check`. An approved token `id()`
contract change is recorded in the plan; it does not authorize unrelated API or
schema changes. For releases, also update the appropriate release record.

## Finish

Review the intended diff without reverting others' work. Summarize the change,
checks run and not run, public API or backward-compatibility impact, and any
remaining risks. Update relevant docs, examples, or ADRs when their behavior or
decisions change. No generated reports or secrets should be committed by
accident.
