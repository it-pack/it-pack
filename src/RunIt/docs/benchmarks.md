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
| Bounded publication gate | `benchBoundedPublish` | Per-execution count + payload-bound checks (native byte estimation) |
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

> Historical note: in the M12 development environment the installed phpbench binary failed to
> start due to a configuration/schema incompatibility unrelated to the benchmark code
> (`phpbench.json` used an invalid top-level `runner` key). Fixed on 2026-08-23 (Phase 2, T2.4):
> the file now only pins the bootstrap; iteration/revs/warmup settings live with the subjects.

## Recorded baselines (2026-08-23)

First machine-readable baselines, measured after the Phase 2 hot-path work. Environment:
PHP 8.5.8 CLI, no xdebug, no opcache, Linux container — figures are environment-relative,
meaningful for future comparisons on the same host, not absolute claims.

| Subject | Mean | Deviation |
|---------|------|-----------|
| `benchNullWrapper` | 0.31 µs | ±5.9% |
| `benchInMemoryExecutionLifecycle` | 182.7 µs | ±41.6% |
| `benchActivityLifecycle` | 208.4 µs | ±36.1% |
| `benchMeasurementCreation` | 220.9 µs | ±39.4% |
| `benchObservationCreation` | 220.8 µs | ±27.0% |
| `benchThrowableNormalization` | 2.11 µs | ±11.1% |
| `benchRedaction` | 3.93 µs | ±9.7% |
| `benchBoundedPublish` | 3.59 µs | ±4.5% |
| `benchBuffering` | 44.10 µs | ±64.3% |

Phase 2 (T2.4) replaced the bounded publisher's per-publish
`strlen(json_encode(...))` sizing — which encoded every occurrence/snapshot
payload just to measure it — with the native
`PayloadSectionByteEstimator`. No pre-change figures exist (the suite could not
run before), so the change is justified analytically (one full payload encoding
eliminated per publish) and pinned going forward by `benchBoundedPublish`.
Regenerate with `composer benchmark` when comparing.
