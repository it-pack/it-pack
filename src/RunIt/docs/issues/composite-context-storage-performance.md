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

## Existing benchmark run

On PHP 8.5.12 CLI NTS on x86-64 WSL2 (Linux 5.10.16.3), with CLI
OPcache and JIT disabled, PHPBench 1.7.0 was run from `src/RunIt`:

```sh
vendor/bin/phpbench run benchmarks/ContextStorageBench.php benchmarks/FiberContextStorageBench.php --config=phpbench.json --report=default
```

Each subject used two warmups and ten iterations of 10,000 revisions. The
following are ranges of iteration averages, in microseconds per revision:

| Workload | Range |
|---|---:|
| Stack attach/detach, no parents | 1.044-3.081 |
| Composite attach/detach, no parents | 2.248-3.162 |
| Stack attach/detach, 63 parents | 1.086-3.892 |
| Composite attach/detach, 63 parents | 2.259-2.872 |
| Fiber storage, two interleaved fibers, one parent each | 3.847-4.019 |
| Fiber storage, two interleaved fibers, 63 parents each | 4.721-5.261 |

The Fiber revision includes two switches and two attach/detach pairs and uses
`FiberContextStorage` directly, not the composite. The synchronous stack is a
different implementation, not the pre-ownership composite baseline; these
numbers do not isolate ownership overhead. The large stack outliers demonstrate
host variability. PHPBench reported process peaks of 9,720,976-9,722,360 bytes
for these stack/composite subjects and 9,725,032 bytes for the Fiber subjects;
these include bootstrap and setup and are not per-token memory measurements.
All 12 subjects completed without errors; PHPBench reports zero assertions,
although the benchmark teardown checks context restoration.

No implementation change is justified by this run: it neither establishes a
representative regression against a reproducible pre-change implementation nor
shows impact on an application. Compare both implementations under the same
nested and composite-interleaved workloads, measure retained and peak memory at
varying live-token counts, and repeat on PHP 8.6 and representative production
configurations. Only PHP 8.5 was measured here.

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
