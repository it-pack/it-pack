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

## Existing benchmark run and assessment

From `src/AsyncIt`, on PHP 8.5.12 CLI NTS, x86-64 WSL2 (Linux
5.10.16.3), CLI OPcache and JIT disabled, PHPBench 1.7.0 was run:

```sh
vendor/bin/phpbench run benchmarks/FiberSuspendResumeBench.php --report=default
```

Five iterations gave 136.140-188.977 microseconds per revision for one
suspension (1,000 revisions each), and 395.780-433.270 microseconds per
revision for ten suspensions (500 revisions each). Reported process peaks were
11,560,392 and 12,197,064 bytes respectively. Both subjects completed without
errors; PHPBench reported zero assertions. These are complete runtime workloads
including scheduling and timers, not isolated token-cleanup or per-token memory
measurements, and there is no matching scan baseline. The benchmark discovers
its runtime rather than explicitly selecting an adapter; the selected adapter
was not recorded, so the result cannot be attributed to a specific adapter.

Code inspection indicates that the shared trait retrieves and then mutates
slot arrays, which can incur PHP copy-on-write costs as same-slot nesting grows;
the earlier synthetic observation does not quantify the actual adapter. The
WeakMap does not retain token keys, but dropping a token does not itself detach
its scope from the slot. Revolt's per-Fiber map uses weak Fiber keys, whereas
the Swoole adapter stores slots by coroutine ID in an array with no removal in
that class. This is a potential lifetime and memory-growth concern, not a
measured leak; measure completed-coroutine behavior and runtime ID reuse before
changing lifecycle semantics. Swoole 6.2.3 was installed but not separately
benchmarked; Swow and other runtime adapters were not measured.

The earlier scan-versus-WeakMap measurements demonstrate the prior scan's
scaling in one Revolt microbenchmark, but the present run cannot establish a
further safe optimization or its production benefit. Retain the implementation
pending repeated adapter-specific nesting, live-token, abandoned-token and
memory tests, with matched baselines and PHP 8.6 coverage.

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
