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

## Additional verification (2026-11-13)

The Fiber storage regression now checks cross-Fiber token rejection, failed
out-of-order detach without stack mutation, and successful LIFO retry. The
interleaving test checks that independent and reused executions have no
unintended parent. On PHP 8.4.27 the focused Fiber storage test passed (8 tests,
31 assertions), concurrency passed (8 tests, 175 assertions), conformance passed
(52 tests, 111 assertions), and the complete RunIt test and quality commands
passed. The RunIt quality command also passed on PHP 8.5.12. Swoole was loaded;
OpenSwoole, Swow and PHP 8.6 were not verified. These results do not establish
the unavailable runtime paths or final downstream compatibility.

**Reference:** [Phase 0 and Phase 1 refactoring plan](../REFACTORING_PLAN.md#phase-0-baseline-and-safety-net).
