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
| Sampling and redaction        | `benchRedaction`                   | Sampling, redaction and in-memory publication of a snapshot         |
| In-memory publication         | `benchBuffering`                   | 200 writes to `InMemoryArtifactPublisher`, not the bounded buffer    |
| Buffered admission            | `BufferedAdmissionBench`           | Normal buffered publish with real byte estimation                   |
| Buffered batch flush          | `BufferedFlushBench`               | One 256-item flush from a full 1,024-item buffer                    |
| Nested snapshots              | `NestedSnapshotBench`              | Projection, key masking, JSON normalization and publication        |
| CloudEvents mapping and JSON  | `CloudEventBench`                  | Occurrence mapping and structured JSON serialization separately    |

Additional adapter and context workloads are discussed below; some use temporary
harnesses rather than the checked-in PHPBench suite.

## Targets

These are historical targets, not verified results. The recorded iteration-average
ranges below do not establish p50 or throughput, and some subjects include an
entire execution rather than an isolated operation.

| Metric                                      | Target            | Status            |
|---------------------------------------------|-------------------|-------------------|
| In-memory execution latency (p50, 100 revs) | < 50 µs           | not established   |
| Activity latency (p50)                      | < 25 µs           | not established   |
| Measurement creation (p50)                  | < 10 µs           | not established   |
| Observation creation (p50)                  | < 15 µs           | not established   |
| Throwable normalization (p50)               | < 20 µs           | not established   |
| Artifact publication throughput             | > 50k artifacts/s | not established   |

## Regression policy

The previously proposed per-PR benchmark and blocking regression gates are not
established by the current workflows. Measurements should record the workload,
hardware, runtime and variance; the figures below are not automatic CI thresholds.

> Historical note: in the M12 development environment the installed phpbench binary failed to
> start due to a configuration/schema incompatibility unrelated to the benchmark code
> (`phpbench.json` used an invalid top-level `runner` key). Fixed on 2026-08-23 (Phase 2, T2.4):
> the file now only pins the bootstrap; iteration/revs/warmup settings live with the subjects.

## Buffered batch flush baseline (2026-11-09)

At clean starting revision `228a7ff9efaa8a5c309fb744992ecf67de92d7f5` plus
the benchmark and this documentation, run from the package directory:

```bash
php -d opcache.enable_cli=0 -d opcache.jit=disable -d pcov.enabled=0 vendor/bin/phpbench run benchmarks/BufferedFlushBench.php --config=phpbench.json --report=default
```

Each iteration constructs and fills a fresh buffer outside the timed region with
1,024 distinct small snapshots and the real byte estimator. The item limit is
1,024, byte limit 8 MiB, maximum age 3,600 seconds, batch size 256, periodic
flush disabled, and overflow strategy `Fail`. The timed operation is one flush
to an in-memory sink. After timing, checks require the first 256 objects in FIFO
order, 768 remaining items, the correct remaining estimated bytes and zero drops;
closing the buffer then verifies the full order, zero remaining bytes and no
drops. These hooks throw on failure; PHPBench reports zero framework assertions.
The benchmark requires one revolution and zero warmups because each flush consumes
the prepared buffer. Fixture creation, estimation during admission and the final
close are not timed.

PHPBench 1.7.0 on PHP 8.5.12 CLI NTS, WSL2 x86-64, four virtual CPUs on an
Intel Core i7-9700, with CLI OPcache, JIT and PCOV disabled and Xdebug absent,
reported a mode of **657.671 µs per batch**, relative standard deviation 4.58%,
and iteration range **642-730 µs**, across ten iterations. No CPU pinning or
in-process warmup was used. A separate PHP 8.4.27 run passed the same controls
and reported 747.614 µs mode and 37.36% relative standard deviation; it ran
concurrently with other checks and is not a controlled version comparison.

This synthetic single-batch measurement includes the clock and in-memory sink,
not network I/O. It excludes overflow, expired items, failures, concurrent
publishers, other batch sizes and memory profiling. It is not production
throughput, a controlled before/after comparison or a regression threshold.
Phase 0 remains incomplete.

## CloudEvents mapping and serialization baseline (2026-11-09)

At clean starting revision `01cd9371f9707d6ab6adbeab2edbb27473b0c928` plus
the benchmark and this documentation, run from the package directory:

```bash
php -d opcache.enable_cli=0 -d opcache.jit=disable -d pcov.enabled=0 vendor/bin/phpbench run benchmarks/CloudEventBench.php --config=phpbench.json --report=aggregate
```

The reusable occurrence has 16 nested item rows, execution and activity IDs,
a subject, correlation ID and one custom extension. The mapping subject maps
that occurrence with the default policy; the serialization subject encodes a
pre-mapped event using the default structured JSON serializer. Fixture creation
and verification are outside the timed subjects. After each iteration the hook
checks envelope fields, extensions, data and a JSON round-trip; failures throw,
although PHPBench reports zero framework assertions.

PHPBench 1.7.0 on PHP 8.5.12 CLI NTS, WSL2 x86-64, four virtual CPUs on an
Intel Core i7-9700, with CLI OPcache, JIT and PCOV disabled and Xdebug absent,
reported these modes and relative standard deviations across five iterations of
1,000 operations, after two warmups:

| Subject | Mode (µs/operation) | Relative standard deviation |
| --- | ---: | ---: |
| Occurrence to CloudEvent | 59.245 | 11.05% |
| CloudEvent to structured JSON | 5.141 | 10.28% |

This unpinned single-process synthetic run is not a regression threshold or a
controlled comparison. It excludes occurrence creation, deserialization timing,
network and sink I/O, buffering, redaction, failures, concurrency, memory
profiling and PHP 8.4 timing. Its fixture and results do not represent all
CloudEvents traffic. A separate PHP 8.4.27 invocation using the same PHPBench
configuration passed both verification hooks, with modes of 60.674 µs for mapping
and 5.190 µs for serialization (relative standard deviations 2.78% and 6.55%).
It overlapped a quality run and used the default PHP configuration, so it is not
a controlled PHP-version comparison. Phase 0 remains open.

## PHPBench baseline (2026-11-09)

At clean starting revision `d957578f4458c9cb26b76c317b4a922b5e12a2d9`,
`composer benchmark -- --report=default` passed all ten subjects (no
assertions). PHPBench 1.7.0 ran PHP 8.5.12 CLI NTS without Xdebug or CLI
OPcache, with two warmups, five iterations and 100 revolutions per iteration.
The following ranges are the minimum and maximum iteration-average times in
microseconds per subject invocation, not per individual artifact:

| Subject | Range (µs) |
| --- | ---: |
| Null wrapper | 0.210-0.230 |
| In-memory execution lifecycle | 77.530-91.760 |
| Activity lifecycle | 114.150-162.680 |
| Measurement creation with execution | 104.830-114.350 |
| Observation creation with execution | 94.950-128.160 |
| Throwable normalization | 1.630-1.850 |
| Sampling and redaction | 2.950-4.970 |
| Bounded publication gate | 2.670-3.510 |
| OTel null-provider record | 0.200-0.220 |
| `benchBuffering` (200 in-memory writes) | 30.680-32.980 |

The snapshot has two payload fields; setup is outside each timed subject.
The bounded publisher uses default security limits. `benchBuffering` constructs
an in-memory publisher per invocation and does not exercise
`BufferedArtifactPublisher`, byte limits, eviction or drain. These synthetic
measurements are not a comparison or a regression threshold; scheduling and
concurrent work can affect them, and several ranges vary substantially. They
do not cover PHP 8.4, concurrency, real export I/O or failure paths. See the
separate byte-bound buffer and nested context baselines below for other workloads.

## Buffered admission baseline (2026-11-09)

At revision `17303aca50509d9e1b3213230ae0ec5849f732c9` plus the new benchmark,
`vendor/bin/phpbench run benchmarks/BufferedAdmissionBench.php --config=phpbench.json --report=default`
measured `BufferedArtifactPublisher::publish()` with the real
`DefaultArtifactByteEstimator`. Preconstructed snapshots contain 2 or 64 nested
items, each with a 64-character label. Each iteration starts with a fresh
buffer; the item limit is 103, the byte limit is 103 times the estimated size,
periodic flushing is disabled, and overflow fails. Setup and verification are
outside timing; verification checks 102 admitted items, estimated-byte accounting
and zero drops, including two warmup publications. The benchmark deliberately
requires its configured two warmups and 100 revolutions; changing those values
without changing its accounting check causes it to fail.

PHPBench 1.7.0 used five iterations of 100 revolutions on PHP 8.5.12 CLI NTS,
WSL2 x86-64, Intel Core i7-9700 with four virtual CPUs; Xdebug and CLI OPcache
were off. Iteration-average ranges were **5.670-14.890 µs/publish** for the
small snapshot and **22.480-26.870 µs/publish** for the large snapshot.
Other checks ran concurrently and the small case has a substantial outlier.
These measurements are not a regression threshold or controlled comparison and
exclude overflow, drain, exporter I/O, concurrent publishers and PHP 8.4 timing.

## Buffered drain and close baseline (2026-11-09)

At revision `f1b49169387ea4e736b5a410084ed011c7c71711`, a temporary harness
measured the existing `BufferedArtifactPublisher`. Each fresh buffer was filled
outside the timed region with one reused real info observation. Its item limit
equalled the queue size, the byte limit was 8 MiB, and a constant estimator
returned 100 bytes per item. Automatic flushing was disabled; the maximum age
was 60 seconds, with a system clock and counting sink. The flush case repeatedly
drained batches of 256; full close had a budget equal to the queue size; limited
close used a budget of 256 and dropped the remainder. Delivery, drop, count and
byte-accounting checks passed. Values are milliseconds for the entire operation,
median and full range across nine samples after two warmups, with case order
rotated between rounds.

| Items | Flush median (range) | Full close median (range) | Limited close median (range) |
| ---: | ---: | ---: | ---: |
| 256 | 0.149 (0.142-0.223) | 0.155 (0.139-0.187) | 0.143 (0.139-0.150) |
| 1,024 | 1.332 (1.276-1.889) | 1.388 (1.288-1.640) | 1.249 (1.203-1.903) |
| 4,096 | 18.107 (17.218-20.042) | 18.215 (17.623-20.033) | 17.510 (17.005-18.634) |
| 16,384 | 284.814 (268.522-323.959) | 283.731 (273.327-341.804) | 279.919 (275.551-295.503) |

PHP 8.5.12 CLI NTS, WSL2 x86-64, four virtual CPUs on Intel Core i7-9700;
CLI OPcache, JIT and PCOV disabled, Xdebug absent, without CPU pinning. Other
quality checks ran concurrently. The temporary harness
`/tmp/kilo/runit-drain-current.php` (SHA-256
`248969b9b120c6f70a80dd8a76f80fea4ce295919cf4c8602159eb3eb5532dad`)
is not durable. While present, run from the package directory:

```bash
php -d memory_limit=-1 -d opcache.enable_cli=0 -d opcache.jit=disable -d pcov.enabled=0 /tmp/kilo/runit-drain-current.php
```

The stress sizes exceed the default 1,024-item maximum. Elapsed time grew
strongly superlinearly in this workload, but the measurement does not isolate
the cost of `array_shift()` or prove causation. It excludes real I/O,
serialization, timed estimation, overflow, expiration, concurrency, failures,
memory profiling and PHP 8.4. It is not a production guarantee, regression
threshold or controlled comparison with the older baseline, whose harness is
unavailable and used a different estimator.

## PSR-3 publication baseline (2026-11-08)

At revision `c4841f341d8c6e8efbcc19ad6dfd85d6eb8aa205`, a temporary
microbenchmark measured actual `ObservationLoggerPublisher` calls with and
without `DefaultArtifactRedactor`, using a counting PSR-3 logger. Values are
microseconds per publication, median and full range over 11 samples of 20,000
calls each after two warmup rounds; case order rotated between rounds.

| Workload | Median | Range |
| --- | ---: | ---: |
| Direct, info | 2.469 | 2.338-3.229 |
| Redactor then PSR-3, info | 3.755 | 3.572-5.131 |
| Direct, three-cause error | 3.303 | 3.069-3.499 |
| Redactor then PSR-3, three-cause error | 5.876 | 5.612-7.507 |

PHP 8.5.12 CLI NTS, WSL2 x86-64, four virtual CPUs on Intel Core i7-9700;
CLI OPcache, JIT and PCOV disabled, Xdebug absent, no CPU pinning. Artifacts
with four attributes, publishers and logger were constructed outside the timed
loop. The synthetic error contained three throwable descriptions. The harness
checks delivery, masking and source immutability. Concurrent development and
baseline work may have affected timing; these results are not a regression
threshold or production throughput. They exclude formatting, I/O, artifact
creation, sampling, buffering, concurrency, failure paths and PHP 8.4.

The temporary `/tmp/kilo/runit-psr3-publication-baseline.php` (SHA-256
`1ab4b97ac7743b6cf03847ca5781c7cbe80999ad4f1ef727838d9a73367b412e`)
is not durable. While available, run it from the package directory with
`php -d memory_limit=-1 -d opcache.enable_cli=0 -d opcache.jit=disable -d pcov.enabled=0 /tmp/kilo/runit-psr3-publication-baseline.php`.

## Nested context-storage baseline (2026-11-08)

At revision `6e369d27662790058496e7f4d283853191a1bdd7`, a temporary harness
measured immediate attach/detach pairs at a pre-existing nesting depth. Values
are nanoseconds per pair: median and full range across 11 samples after two
warmups, with 20,000 pairs per sample and rotated case order.

| Storage | Existing depth | Median | Range |
| --- | ---: | ---: | ---: |
| Stack | 0 | 527.58 | 501.52-611.99 |
| Stack | 7 | 529.90 | 508.18-559.19 |
| Stack | 63 | 529.07 | 513.02-5,504.74 |
| Stack | 255 | 553.29 | 525.53-3,620.57 |
| Fiber | 0 | 855.30 | 805.63-8,444.93 |
| Fiber | 7 | 884.46 | 854.17-956.09 |
| Fiber | 63 | 1,268.72 | 1,247.02-5,461.96 |
| Fiber | 255 | 2,628.49 | 2,555.38-7,674.60 |

PHP 8.5.12 CLI NTS on WSL2 x86-64, Intel Core i7-9700, four virtual CPUs;
CLI OPcache, JIT and PCOV were disabled. A real scope was reused, with setup,
current-scope checks and LIFO cleanup outside the timed region. Fiber cases ran
inside a Fiber. The temporary harness is `/tmp/kilo/runit-nested-context-bench.php`
(SHA-256 `0d8697743671f1ef53953835f96f4cf8ef3b65cc9e778e7b3c0e0f4b4d6551f2`),
which is not durable. While available, run it from the package directory with
`php -d memory_limit=-1 -d opcache.enable_cli=0 -d opcache.jit=disable -d pcov.enabled=0 /tmp/kilo/runit-nested-context-bench.php`.
The Fiber median grew with depth; large outliers preclude
using this as a regression threshold or identifying a cause. This synthetic
single-process test excludes scope creation, scheduling, interleaving, composite
and coroutine storage, memory profiling, failure paths and PHP 8.4.

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

### Buffer overflow and priority baseline (2026-11-08)

At revision `6d602fe384db04d76c6ba68cf78f7a2154cb6a4e` the worktree was
clean before this documentation change. The existing `benchBuffering` does not
exercise the buffered publisher, and the drain/close measurement excludes
overflow. This baseline fills a fresh `BufferedArtifactPublisher` outside the
timed region, then times 128 consecutive publishes into the full count-bound
queue. Values are microseconds per publish: median and full range of 11 sample
averages, after two warmup rounds, with case order rotated each round.

| Full queue / overflow case | 256 items | 1,024 items | 4,096 items |
|----------------------------|----------:|------------:|------------:|
| Drop oldest, incoming high | 1.594 (1.583–1.978) | 3.173 (2.989–12.601) | 9.246 (8.941–10.363) |
| Priority, reject incoming low | 0.899 (0.873–1.496) | 0.904 (0.886–1.207) | 0.930 (0.898–1.205) |
| Priority, low items at front | 2.644 (2.536–7.268) | 6.382 (5.677–9.826) | 24.153 (23.120–33.869) |
| Priority, 128 low items at tail | 12.222 (11.540–39.478) | 73.937 (69.019–88.743) | 349.744 (324.253–374.240) |
| Priority, all high | 20.791 (20.061–73.284) | 84.183 (77.136–98.860) | 337.253 (307.577–405.166) |

The oldest, reject-low and low-front cases start with all low-priority
observations. The tail case starts with high-priority observations followed by
128 low-priority observations; the all-high case contains only high-priority
observations. Except for reject-low, each incoming observation is high priority.
The tail case successively removes those 128 low items, while all-high scans the
entire queue then drops the oldest; neither is an isolated scan measurement.
Priority classifies reused real info/error observations. A deterministic clock,
constant 100-byte estimator, disabled interval flushing and an effectively
unbounded byte limit isolate count overflow. Filling, flushing and correctness
checks are outside the timed region; every sample verifies count, drops, byte
accounting and delivered high/low totals. The 1,024 count is the default; the
other sizes are stress comparisons, not default configurations.

PHP 8.5.12 CLI NTS, unlimited memory, CLI OPcache/JIT and PCOV disabled, Xdebug
absent; WSL2 x86-64, four virtual CPUs on Intel Core i7-9700. The temporary,
untracked harness is `/tmp/kilo/runit-overflow-priority-baseline.php`. While it
remains available, run from the package directory:

```bash
php -d memory_limit=-1 -d opcache.enable_cli=0 -d opcache.jit=disable -d pcov.enabled=0 /tmp/kilo/runit-overflow-priority-baseline.php
```

The output includes individual sample averages. These single-process, unpinned
measurements have substantial outliers and are not regression thresholds or
production throughput estimates. They include item construction, priority
classification, clock and estimator calls, and loop overhead, but exclude real
estimation costs, health reporting, sink I/O, expiration, byte-bound overflow,
blocking/fail strategies, concurrent traffic, memory profiling and PHP 8.4.
The scan and array operations both contribute; these results do not attribute
their individual costs or demonstrate behavior under a realistic priority mix.

### Byte-bound buffer overflow baseline

At revision `8fdbb0f19ea236c226f1ed82529c932bc0f3c6ee`, with unrelated
worktree edits present, a focused `DropOldest` test filled a real buffered
publisher with 1,024 observations estimated at 100 bytes each. The byte limit
was 102,400 and the item limit 2,048, so the subsequent 128 timed publishes
triggered the byte limit rather than the count limit. Each sample used a fresh
publisher; prefill, validation and flush were outside the timed region. All
samples verified 128 drops, 1,024 retained items and 102,400 estimated bytes,
then 1,024 deliveries in survivor order and zero remaining bytes after flush.

After two warmups, 11 batch samples had a median of 530,576 ns per 128
publishes (4.145 microseconds per publish), ranging from 479,671 to 1,591,829
ns per batch (3.747 to 12.436 microseconds per publish). Batch samples in ns:
1,591,829; 530,576; 538,745; 928,958; 479,671; 600,809; 513,441; 486,843;
513,541; 510,254; 716,866.

PHP 8.5.12 CLI on x86-64 WSL2, with CLI OPcache, JIT and PCOV disabled and
garbage collection enabled. The temporary harness is
`/tmp/kilo/runit-drop-oldest-baseline.php`:

```bash
php -d opcache.enable_cli=0 -d pcov.enabled=0 /tmp/kilo/runit-drop-oldest-baseline.php
```

This single-process microbenchmark includes publisher and loop costs but not
real estimator cost, sink I/O, concurrency or flush performance. It does not
test variable-size projected admission or establish production throughput.
The temporary harness is not durable; the variability precludes using this as
a regression threshold.

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

### Reproducible nested snapshot benchmark (2026-11-09)

`benchmarks/NestedSnapshotBench.php` provides a durable synthetic workload for
8 and 64 checkout-like rows, with two configured sensitive keys per row. It
checks every mask, source immutability, JSON normalization and sink output outside
the timed subjects. The publisher uses a JSON-encoding in-memory sink; projection
does not perform deep serialization. Run from the package directory:

```bash
php -d opcache.enable_cli=0 -d opcache.jit=disable -d pcov.enabled=0 vendor/bin/phpbench run benchmarks/NestedSnapshotBench.php --config=phpbench.json --report=aggregate
```

At starting revision `5ee134f43dcae8789ebc78ccb98b435793babd12`, with the new
benchmark and concurrent test and documentation work, PHPBench 1.7.0 on PHP
8.5.12 CLI NTS reported the following modes and relative standard deviations
in microseconds per operation. Each subject used two warmups and five iterations
of 100 operations. The host was WSL2 x86-64, four virtual CPUs on an Intel
Core i7-9700; CLI OPcache, JIT and PCOV were disabled and Xdebug was absent.

| Workload | 8 rows | 64 rows |
| --- | ---: | ---: |
| Snapshot projection | 1.453 (7.29%) | 1.411 (13.18%) |
| Configured redaction | 26.813 (7.30%) | 224.142 (5.23%) |
| JSON normalization | 47.591 (9.56%) | 342.713 (3.07%) |
| Redacting publisher | 33.388 (2.11%) | 218.040 (5.97%) |

The prior temporary harness is unavailable and its fixture and method are not
identical, so these results are not a controlled comparison. Concurrent activity,
unpinned execution and observed variability limit interpretation; these are not
regression thresholds or production throughput estimates. They exclude creation
and hashing, real I/O, buffering, concurrency, failure paths and PHP 8.4 timing.
The broader Phase 0 benchmark baseline remains open.

Phase 2 (T2.4) replaced the bounded publisher's per-publish
`strlen(json_encode(...))` sizing — which encoded every occurrence/snapshot
payload just to measure it — with the native
`PayloadSectionByteEstimator`. No pre-change figures exist (the suite could not
run before), so the change is justified analytically (one full payload encoding
eliminated per publish) and pinned going forward by `benchBoundedPublish`.
Regenerate with `composer benchmark` when comparing.

### Execution span store retention baseline (Phase 5 preparation, 2026-11-08)

At revision `5474bfb78d2f811a99667d80b7ccd10ce453d431`, an untracked
standalone harness exercised the existing `InMemoryExecutionSpanStore` directly,
without production changes. Seven samples ran in one process, each creating a
fresh store at each size in ascending order, then releasing it before the next
case. Sizes below are **pairs**: one execution and one activity entry per pair.
Each has a distinct real OpenTelemetry `SpanContext`, null trace state, and
fixed-width synthetic IDs (33-byte store keys, 32-byte trace IDs and 16-byte
span IDs). Thus 100,000 pairs means 200,000 retained contexts. Autoloading and
one context construction precede measurements. The harness checks the last
entry and all timed lookup outcomes; it does not simulate actual publishers.

| Pairs | PHP used-byte delta, median (range) | Hit ns/lookup, median (range) | Miss ns/lookup, median (range) |
|------:|------------------------------------:|------------------------------:|-------------------------------:|
| 0 | 80 (80–80) | N/A | 105.458 (92.157–141.933) |
| 1,000 | 1,490,112 (1,490,112–1,498,304) | 145.887 (119.576–232.170) | 114.591 (102.625–138.068) |
| 10,000 | 15,390,912 (15,390,912–15,636,672) | 194.694 (155.517–253.500) | 113.124 (101.647–138.451) |
| 100,000 | 151,286,000 (151,286,000–153,121,032) | 310.162 (211.434–431.107) | 128.213 (106.429–188.564) |

Each timing is an average of 200,000 calls, alternating execution and activity
lookups over 256 deterministic precomputed key pairs; hits precede misses.
The timed loop includes key selection, method calls and result checks, so these
are not isolated hash-table costs. There are no separate warmup samples, case
order is fixed, and the wide ranges and small repeatedly accessed key set limit
interpretation. Memory is `memory_get_usage(false)` after filling minus before
creating the store, after garbage collection; it includes PHP object and key
allocations, not just map buckets. The corresponding `memory_get_usage(true)`
delta at 100,000 pairs ranged from 10,485,760 to 138,412,032 bytes (median
10,485,760): earlier cases leave allocator pages available for reuse. Neither
metric is process RSS, a peak, a fresh-worker baseline, nor a measurement of
memory returned to the OS. Context sharing, trace state, ID lengths, other
worker allocations and allocator history can materially change the result.

PHP 8.5.12 CLI NTS, unlimited PHP memory limit, CLI OPcache/JIT and PCOV
disabled, Xdebug absent; WSL2 x86-64, four virtual CPUs on Intel Core i7-9700.
The synthetic sizes illustrate retained growth while the store is alive, not
an actual traffic rate, deployment memory budget, concurrency behavior, or a
justification for any particular retention limit. The store has no removal or
bound, and a long-lived store with unique IDs can accumulate entries; overwrite,
parent-lookup correctness, real SDK lifetimes, RSS and PHP 8.4 are not measured.
No regression threshold or retention limit is selected from this baseline.

The exact untracked harness and raw per-sample results were stored at
`/tmp/kilo/runit-span-store-baseline.php` and
`/tmp/kilo/runit-span-store-results.json`; neither was present on 2026-11-09.
The table is therefore historical evidence, not currently reproducible with the
original harness. The original command, which overwrote the results file, was:

```bash
php -d memory_limit=-1 -d opcache.enable_cli=0 -d opcache.jit=disable -d pcov.enabled=0 /tmp/kilo/runit-span-store-baseline.php
```

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
