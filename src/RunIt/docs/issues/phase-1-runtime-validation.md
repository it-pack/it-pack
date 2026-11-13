# Complete context ownership and runtime validation

**GitHub issue:** https://github.com/it-pack/it-pack/issues/40

## Problem

Phase 0 is complete as a documented baseline with the limitations in the plan.
Phase 1's checklist is checked, but runtime-specific and performance follow-ups
remain. On 2026-11-13, the conformance and concurrency suites passed on PHP
8.4.27, including native Swoole; OpenSwoole and Swow were unavailable. The
synchronous default is intentionally not Fiber-isolated: the regression test
demonstrates cross-Fiber ambient scope exposure and an unintended parent relationship.

## Follow-up

- Use the documented Phase 0 baseline and its explicit measurement gaps when
  comparing affected workloads; do not treat it as a representative benchmark.
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
