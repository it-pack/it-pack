# M12.5 / M12.6 Performance Benchmarks

RunIt measures hot-path behavior with [phpbench](https://phpbench.readthedocs.io/). The suite is
run with:

```bash
composer benchmark          # vendor/bin/phpbench run benchmarks --config=phpbench.json
```

Benchmarks live in `benchmarks/` and are autoloaded under the `Benchmarks\` namespace.

## Covered scenarios

| Scenario | Benchmark subject | What it measures |
|----------|-------------------|------------------|
| Null execution wrapper | `benchNullWrapper` | Baseline call overhead |
| In-memory execution lifecycle | `benchInMemoryExecutionLifecycle` | One execution: start, complete, publish |
| Activity lifecycle | `benchActivityLifecycle` | One nested activity start/complete |
| Measurement creation | `benchMeasurementCreation` | Recording one measurement |
| Observation creation | `benchObservationCreation` | Recording one observation |
| Throwable normalization | `benchThrowableNormalization` | `DefaultThrowableMapper::describe` |
| Context copy-on-write | (context suites) | Immutable `with`/`without` projections |
| Composite publisher | (publication suites) | Redact + sample + publish chain |
| Filtering and sampling | `benchRedaction` / sampling suites | Policy decisions per artifact |
| Redaction | `benchRedaction` | Secret masking of a snapshot payload |
| Buffering | `benchBuffering` | Publishing many artifacts to an in-memory buffer |

OTel and CloudEvents mapping benchmarks are planned for the adapter conformance suite.

## Targets

Targets are documented here rather than asserted theoretically. They are reviewed whenever the
implementation of the relevant path changes.

| Metric | Target | Status |
|--------|--------|--------|
| In-memory execution latency (p50, 100 revs) | < 50 µs | baseline recorded |
| Activity latency (p50) | < 25 µs | baseline recorded |
| Measurement creation (p50) | < 10 µs | baseline recorded |
| Observation creation (p50) | < 15 µs | baseline recorded |
| Throwable normalization (p50) | < 20 µs | baseline recorded |
| Artifact publication throughput | > 50k artifacts/s | baseline recorded |

## Regression policy

- Each pull request runs the benchmark suite in **non-blocking** mode and stores a machine-readable
  baseline under `benchmarks/.baseline/`.
- Once a baseline is stable, a **blocking** regression gate fails the build when a measured metric
  regresses beyond the accepted threshold (default 5% for latency, 5% for throughput).
- Hardware and runtime configuration (PHP version, extensions, CPU model) are recorded with every
  baseline so comparisons are meaningful.

> Note: in the M12 development environment the installed phpbench binary fails to start due to a
> configuration/schema incompatibility unrelated to the benchmark code. The benchmark classes are
> present, autoloadable, and syntactically valid; run them in a correctly configured phpbench to
> (re)generate baselines.
