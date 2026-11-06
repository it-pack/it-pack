# Assess AsyncIt context-token cleanup cost

**GitHub issue:** https://github.com/it-pack/it-pack/issues/37

## Problem

AsyncIt's `CoroutineContextStorageTrait` previously scanned weak references on
attachment to prune expired entries. This avoided retaining abandoned tokens and
verified exact identity, but attachment cost grew with tracked tokens. It now
uses a `WeakMap` keyed by the exact token object and no longer performs that scan.

A single informal Revolt measurement with 200 live tokens and 10,000
attach/detach pairs took 221.235 ms with the scan versus 77.895 ms with an
experimental `WeakMap` implementation. This is not a general performance
estimate. At the time, direct `WeakMap` construction conflicted with AsyncIt's
factory-enforcement rule, so the alternative was not retained. The rule now
exempts PHP internal classes.

On PHP 8.5.12 CLI NTS with CLI OPcache and JIT disabled, the actual Revolt
adapter was measured with 10,000 attach/detach pairs in a worker Fiber while
other Fibers held live tokens. After one warmup, five samples gave these medians:

| Live tokens | Weak-reference scan | WeakMap |
|---:|---:|---:|
| 0 | 25.512 ms | 24.504 ms |
| 10 | 33.361 ms | 24.377 ms |
| 200 | 173.238 ms | 24.904 ms |
| 1,000 | 781.529 ms | 25.540 ms |

The WSL2 x86-64 microbenchmark does not measure event-loop scheduling or
production throughput. A separate synthetic same-slot nesting workload still
scaled with nesting depth; it was not a measurement of the Revolt adapter.

## Follow-up

- Reproduce and benchmark representative token counts, nesting and runtime
  adapters with repeated samples and recorded environments.
- Measure memory usage and assess nested-slot behavior and other runtime adapters
  before determining whether further optimization is warranted.
- Preserve exact token ownership, collision rejection, LIFO and retry behavior,
  and ensure abandoned tokens are not retained.
- Document results and any remaining resource-lifetime tradeoffs in AsyncIt
  and the RunIt refactoring plan.

**Reference:** [Phase 1 refactoring plan](../REFACTORING_PLAN.md#phase-1-compatibility-and-context-ownership).
