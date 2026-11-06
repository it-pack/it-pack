# Complete context ownership and runtime validation

**GitHub issue:** https://github.com/it-pack/it-pack/issues/40

## Problem

Phase 1 is not complete: its exit depends on the unfinished Phase 0 baseline,
and runtime-specific validation is incomplete. PHP 8.4 and native Swow were not
tested in the most recent work; OpenSwoole was also not tested. The synchronous
default is intentionally not Fiber-isolated: the regression test demonstrates
cross-Fiber ambient scope exposure and an unintended parent relationship.

## Follow-up

- Re-establish the Phase 0 evidence specified in the refactoring plan,
  including exact revisions, dependency and extension state, command results,
  Unit-only coverage evidence and representative benchmarks.
- Run relevant ownership, conformance and interleaving tests on PHP 8.4 and
  available supported newer versions, including real Swoole, OpenSwoole and
  Swow environments where applicable. Record unavailable environments and skips.
- Complete the remaining runtime-specific isolation, LIFO, parent identity,
  exception restoration and repeated-request cases in the appropriate suites.
- Determine feasible misuse validation without changing the synchronous default
  or introducing global mutable state; obtain approval before changing behavior.
- Verify migration and compatibility documentation and the Phase 1 exit criteria
  against the final tree. Preserve the approved non-unique token ID contract.

**Reference:** [Phase 0 and Phase 1 refactoring plan](../REFACTORING_PLAN.md#phase-0-baseline-and-safety-net).
