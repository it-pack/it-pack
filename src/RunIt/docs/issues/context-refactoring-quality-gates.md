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

**Reference:** [RunIt refactoring plan](../REFACTORING_PLAN.md#phase-1-compatibility-and-context-ownership).
