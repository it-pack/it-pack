# Measure composite context storage ownership overhead

**GitHub issue:** https://github.com/it-pack/it-pack/issues/38

## Problem

`CompositeContextStorage` now tracks issued wrapper tokens by object identity to
reject foreign and forged tokens. This enforces the context-storage contract, but
adds work to the attach/detach path.

On PHP 8.5.12 CLI NTS, with CLI OPcache and JIT disabled, a synchronous depth-one
microbenchmark measured a median of 1,940.57 ns per attach/detach pair before the
change and 2,209.06 ns after it, an increase of 13.8%. Each variant had 20,000
warm-up pairs and 12 alternating samples of 100,000 pairs using the same scope
and `StackContextStorage`. The sample ranges were 1,849.77-2,219.50 ns and
2,116.74-3,456.29 ns respectively. The high final sample indicates variability.
This is not an end-to-end or concurrent workload.

## Follow-up

- Benchmark representative nested and concurrent workloads, memory usage, and
  supported runtime versions against a reproducible pre-change baseline.
- Assess whether the measured overhead matters in realistic workloads and
  optimize only if justified by evidence.
- Preserve exact issued-token identity, owner delegation, LIFO behavior and
  retry after a failed detach. Do not rely on `id()` for token ownership.
- Record the workload, environment, variance, and results in the relevant
  performance documentation and refactoring plan.

**Reference:** [Phase 1 refactoring plan](../REFACTORING_PLAN.md#phase-1-compatibility-and-context-ownership).
