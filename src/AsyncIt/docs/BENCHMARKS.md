# AsyncIt Benchmark Baselines

Baseline measurements for the scheduling and poll hot paths, produced with
`vendor/bin/phpbench run benchmarks/` (PHP 8.5.12, opcache/xdebug off).

These are **baselines only** — no improvement is claimed without a subsequent
measurement (AGENTS.md performance requirements).

## Method

```
vendor/bin/phpbench run benchmarks/ --report=aggregate
```

Configuration: `phpbench.json` (`runner.bootstrap: vendor/autoload.php`).

## Baselines

| Benchmark | Subject | Mode | Revs | Iterations | Mem peak | RStDev |
|---|---|---|---|---|---|---|
| `RuntimeBenchmarks::benchRunSimpleOperation` | task spawn + completion | 105.118μs | 1000 | 5 | 10.621mb | ±1.84% |
| `RuntimeBenchmarks::benchRunWithAwaitDelay` | Fiber suspend/resume (`awaitDelay`) | 129.281μs | 1000 | 5 | 10.929mb | ±4.43% |
| `PollerBenchmarks::benchRegisterRelease` | poll-handle register/release | 0.654μs | 10000 | 5 | 8.432mb | ±1.54% |

## Files

- `benchmarks/RuntimeBenchmarks.php` — P10.1 spawn/completion, P10.2 Fiber suspend/resume
- `benchmarks/PollerBenchmarks.php` — P10.3 poll-handle register/release

## Re-running

```
composer dump-autoload   # once, after adding benchmarks
vendor/bin/phpbench run benchmarks/ --report=aggregate
```
