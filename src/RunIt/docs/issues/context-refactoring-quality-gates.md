# Resolve context refactoring quality gate failures

**GitHub issue:** https://github.com/it-pack/it-pack/issues/39

## Problem

Quality checks during the Phase 1 context-ownership work did not all pass. These
are recorded observations, not proof that each diagnostic predates the work:

- RunIt `composer quality` stopped at `cs:check`, which reported formatting
  differences in 32 files. Later stages were not run by that command.
- RunIt `composer analyse` reported 141 diagnostics, including three on existing
  assertions in the modified concurrency test; full attribution to the committed
  baseline was not established.
- RunIt `composer lint:length` reported four violations in publication and SDK
  files. `composer api:check` reported nine additive symbols, zero backward-
  compatibility breaks and exited nonzero.
- AsyncIt `composer analyse` reported 31 unrelated errors, and its architecture
  check could not run because of a parse error in `phparkitect.php` at line 257.

## Follow-up

- Reproduce each failure on the current tree and attribute it to the appropriate
  change or pre-existing baseline before making corrections.
- Fix actionable formatting, static analysis, line-length and architecture
  failures without suppressing rules or adding baselines to hide them.
- Review the nine API additions and their compatibility and release implications.
  Do not regenerate the API or schema baseline without explicit approval.
- Re-run the affected package quality gates and record exact results, skipped
  stages and environment limitations in the respective documentation.

## Current verification (2026-11-13)

RunIt `composer quality --quiet` passed on PHP 8.4.27 and 8.5.12, including
the strict API check; no baseline was regenerated. AsyncIt static analysis and
architecture now pass after correcting type information and the architecture
check's interface-name handling. AsyncIt `composer test --quiet` and `cs:check`
also passed on PHP 8.5.12. AsyncIt `composer quality --quiet` stops at strict
Composer validation because its existing `it-pack/run-it` `@dev` constraint is
unbounded. Later stages were not run by that quality command; the separate
checks above do not establish a complete quality pass. The separate Rector dry
run now passes after making the fixture discoverable to PHPStan and using its
canonical path. Resolve the dependency constraint and rerun the full gate without
suppressing validation.

**Reference:** [RunIt refactoring plan](../REFACTORING_PLAN.md#phase-1-compatibility-and-context-ownership).
