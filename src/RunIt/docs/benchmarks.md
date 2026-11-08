# M12.5 / M12.6 Performance Benchmarks

RunIt measures hot-path behavior with [phpbench](https://phpbench.readthedocs.io/). The suite is
run with:

```bash
composer benchmark          # vendor/bin/phpbench run benchmarks --config=phpbench.json
```

Benchmarks live in `benchmarks/` and are autoloaded under the `Benchmarks\` namespace.

## Covered scenarios

| Scenario                      | Benchmark subject                  | What it measures                                                    |
|-------------------------------|------------------------------------|---------------------------------------------------------------------|
| Null execution wrapper        | `benchNullWrapper`                 | Baseline call overhead                                              |
| In-memory execution lifecycle | `benchInMemoryExecutionLifecycle`  | One execution: start, complete, publish                             |
| Activity lifecycle            | `benchActivityLifecycle`           | One nested activity start/complete                                  |
| Measurement creation          | `benchMeasurementCreation`         | Recording one measurement                                           |
| Observation creation          | `benchObservationCreation`         | Recording one observation                                           |
| Throwable normalization       | `benchThrowableNormalization`      | `DefaultThrowableMapper::describe`                                  |
| Bounded publication gate      | `benchBoundedPublish`              | Per-execution count + payload-bound checks (native byte estimation) |
| OTel bridge no-op path        | `benchOtelNullProviderRecord`      | Instrument-mapper record without a meter provider                   |
| Context copy-on-write         | (context suites)                   | Immutable `with`/`without` projections                              |
| Composite publisher           | (publication suites)               | Redact + sample + publish chain                                     |
| Filtering and sampling        | `benchRedaction` / sampling suites | Policy decisions per artifact                                       |
| Redaction                     | `benchRedaction`                   | Secret masking of a snapshot payload                                |
| Buffering                     | `benchBuffering`                   | Publishing many artifacts to an in-memory buffer                    |

OTel and CloudEvents mapping benchmarks are planned for the adapter conformance suite.

## Targets

Targets are documented here rather than asserted theoretically. They are reviewed whenever the
implementation of the relevant path changes.

| Metric                                      | Target            | Status            |
|---------------------------------------------|-------------------|-------------------|
| In-memory execution latency (p50, 100 revs) | < 50 µs           | baseline recorded |
| Activity latency (p50)                      | < 25 µs           | baseline recorded |
| Measurement creation (p50)                  | < 10 µs           | baseline recorded |
| Observation creation (p50)                  | < 15 µs           | baseline recorded |
| Throwable normalization (p50)               | < 20 µs           | baseline recorded |
| Artifact publication throughput             | > 50k artifacts/s | baseline recorded |

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

| Subject                           | Mean     | Deviation                               |
|-----------------------------------|----------|-----------------------------------------|
| `benchNullWrapper`                | 0.31 µs  | ±5.9%                                   |
| `benchInMemoryExecutionLifecycle` | 182.7 µs | ±41.6%                                  |
| `benchActivityLifecycle`          | 208.4 µs | ±36.1%                                  |
| `benchMeasurementCreation`        | 220.9 µs | ±39.4%                                  |
| `benchObservationCreation`        | 220.8 µs | ±27.0%                                  |
| `benchThrowableNormalization`     | 2.11 µs  | ±11.1%                                  |
| `benchRedaction`                  | 3.93 µs  | ±9.7%                                   |
| `benchBoundedPublish`             | 3.59 µs  | ±4.5%                                   |
| `benchBuffering`                  | 44.10 µs | ±64.3%                                  |
| `benchOtelNullProviderRecord`     | ≈0.29 µs | at noise floor (see T3.5 verdict below) |

### Existing hot-path suite (2026-11-06)

`composer benchmark` passed at revision `6c1fad0ac7f67f0d9612d549051c127f46066c1e`
with test-only worktree changes; production code was unchanged. PHPBench 1.7.0,
PHP 8.5.12 CLI NTS, xdebug and CLI OPcache disabled, WSL2 x86-64 on an Intel
Core i7-9700 with four virtual CPUs. Each subject uses two warmups, five
iterations and 100 revolutions. Values below are PHPBench's reported mode and
deviation, in microseconds per revolution; this run did not retain raw iterations.

| Subject                           |    Mode | Deviation |
|-----------------------------------|--------:|----------:|
| `benchNullWrapper`                |   0.230 |     0.00% |
| `benchInMemoryExecutionLifecycle` |  82.919 |     2.21% |
| `benchActivityLifecycle`          | 116.241 |     3.79% |
| `benchMeasurementCreation`        |  98.185 |     0.96% |
| `benchObservationCreation`        |  98.192 |     3.23% |
| `benchThrowableNormalization`     |   1.792 |     5.82% |
| `benchRedaction`                  |   3.041 |     3.77% |
| `benchBoundedPublish`             |   2.771 |     3.67% |
| `benchOtelNullProviderRecord`     |   0.210 |     3.74% |
| `benchBuffering`                  |  31.601 |     1.08% |

There were ten subjects and no benchmark errors or assertions. The buffering
subject publishes 200 artifacts to `InMemoryArtifactPublisher`, not to
`BufferedArtifactPublisher`; it does not replace the drain/close baseline below.
These synthetic results are environment-relative, not a production throughput
guarantee or a controlled comparison with older runs.

### Buffer drain and close baseline (2026-11-06)

At revision `72c32f63c88c44913bc88c5b42e4673c36737174`, a focused harness
filled a fresh `BufferedArtifactPublisher` outside the timed region, then timed
either repeated 256-item flushes or a single close. Close used either a full
budget or a 256-item budget, dropping the remainder. It checked delivery, drop,
queue and byte totals. Results are milliseconds per operation, median and full
range over nine samples after two warmups with rotating case order:

|  Items |                     Flush |         Full-budget close |     Close with budget 256 |
|-------:|--------------------------:|--------------------------:|--------------------------:|
|    256 |       0.234 (0.215–0.241) |       0.216 (0.210–0.283) |       0.229 (0.209–0.355) |
|  1,024 |       1.708 (1.665–1.929) |       1.731 (1.648–1.968) |       1.444 (1.412–8.665) |
|  4,096 |    19.543 (18.997–22.411) |    19.550 (18.989–20.749) |    18.594 (17.577–19.877) |
| 16,384 | 292.483 (282.612–440.644) | 286.980 (281.001–306.188) | 280.919 (276.619–344.994) |

PHP 8.5.12 CLI NTS, CLI OPcache/JIT disabled, unlimited memory, WSL2 x86-64,
four virtual CPUs, Intel Core i7-9700. The 1,024-item case uses the default
maximum count and batch size with automatic flushing disabled; larger queues
and the limited shutdown budget are stress configurations. The reused info
observation, default estimator, system clock and counting sink exclude actual
sink I/O, serialization, overflow, priority, expiration, concurrency and memory
profiling. Larger queues show superlinear growth, but the measurement does not
isolate its cause or establish production latency. The temporary harness and
samples are `/tmp/kilo/runit-buffer-baseline.php` and
`/tmp/kilo/runit-buffer-baseline-results.txt`; neither is tracked.

### Nested snapshot redaction and serialization (2026-11-08)

At revision `bc12bc5dad009c0210c90000aaaa0f1f440b701b`, a focused synthetic
baseline used real snapshots with nested checkout-like payloads. The 8-row and
64-row fixtures encoded to 1,164 and 9,108 bytes respectively, with 16 and 128
configured key masks. Median and full range below are microseconds per
operation over 11 sample averages, after two warmup rounds. Each sample used
1,000 operations for 8 rows and 300 for 64 rows; case order rotated.

| Workload                                       |                 8 rows |                    64 rows |
|------------------------------------------------|-----------------------:|---------------------------:|
| `Snapshot::normalize()`                        |    0.607 (0.586–0.676) |        0.607 (0.591–0.841) |
| Configured `DefaultArtifactRedactor::redact()` | 16.042 (14.942–35.605) |  109.366 (104.034–137.497) |
| `JsonSnapshotSerializer::normalize()`          | 32.247 (31.488–73.790) |  242.741 (231.743–538.300) |
| Redacting publisher with JSON-encoding sink    | 23.268 (22.362–39.641) | 153.423 (146.582–1527.889) |

PHP 8.5.12 CLI NTS, WSL2 x86-64, four virtual CPUs on Intel Core i7-9700;
CLI OPcache, JIT and PCOV disabled, Xdebug absent, unlimited memory. Fixtures
were created outside the timed region; representative masks, immutability,
serializer round-trip and sink output were checked. The publisher case uses a
custom JSON-encoding sink, not `JsonSnapshotSerializer`. `Snapshot::normalize()`
is a projection rather than deep serialization. The existing `benchRedaction`
uses a small snapshot with no configured sensitive data keys and does not cover
this recursive masking workload.

These unpinned single-process results include wide outliers and are not a
regression threshold or production throughput measurement. They exclude
creation and hashing, actual I/O, buffering, adapter encoding, concurrency,
memory profiling, failure paths, and PHP 8.4. The temporary harness and raw
samples are `/tmp/kilo/runit-redaction-serialization-baseline.php` and
`/tmp/kilo/runit-redaction-serialization-results.txt`; they are not tracked.
While available, rerun from the package directory with:

```bash
php -d opcache.enable_cli=0 -d opcache.jit=disable -d pcov.enabled=0 /tmp/kilo/runit-redaction-serialization-baseline.php
```

Phase 2 (T2.4) replaced the bounded publisher's per-publish
`strlen(json_encode(...))` sizing — which encoded every occurrence/snapshot
payload just to measure it — with the native
`PayloadSectionByteEstimator`. No pre-change figures exist (the suite could not
run before), so the change is justified analytically (one full payload encoding
eliminated per publish) and pinned going forward by `benchBoundedPublish`.
Regenerate with `composer benchmark` when comparing.

### Redaction default (Phase 1, T1.1, 2026-08-24)

The builder now installs a deny-by-default redactor (throwable-message masking)
unless explicitly configured otherwise. Measured per-artifact cost of passing
through the redacting stage on the snapshot path:

| Subject                                       | Mean    | Deviation |
|-----------------------------------------------|---------|-----------|
| `benchBoundedPublish` (no redaction stage)    | 3.52 µs | ±20.1%    |
| `benchRedaction` (sampling → redaction chain) | 4.58 µs | ±10.6%    |

The redaction stage costs ≈0.5–1.0 µs per artifact in this environment
(projection walk + mask comparison). This is the price of deny-by-default
protection; consumers publishing high volumes of non-sensitive artifacts can
opt out via `withRedactor(null)` and should re-measure.

### Lazy initialization verdict (Phase 3, T3.5)

`DefaultMeasurementInstrumentMapper::record()` with a null meter provider
measures **≈0.29 µs per record** (`benchOtelNullProviderRecord`, at the
measurement noise floor). The path is already a single null-check short-circuit;
there is no eagerly constructed infrastructure to defer, so the AGENTS.md
lazy-objects recommendation was **rejected by measurement** rather than
implemented speculatively. Revisit only if the bridge ever grows eager
per-instance construction on an optional path.
