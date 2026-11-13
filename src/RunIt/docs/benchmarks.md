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
| Buffered overflow             | `BufferedOverflowBench`            | Count- and byte-triggered DropOldest batches                        |
| Buffered flush and close      | `BufferedFlushBench`               | One 256-item flush or full-budget close of 1,024 items              |
| Nested snapshots              | `NestedSnapshotBench`              | Projection, key masking, JSON normalization and publication        |
| CloudEvents mapping and JSON  | `CloudEventBench`                  | Occurrence mapping and structured JSON serialization separately    |
| Synchronous context storage   | `ContextStorageBench`              | Stack and composite attach/detach and lookup at two depths          |
| Interleaved Fiber storage     | `FiberContextStorageBench`         | Two Fiber switches and attach/detach pairs at two nesting depths     |

Additional adapter and context workloads are discussed below; some use temporary
harnesses rather than the checked-in PHPBench suite.

## Focused Synthetic SDK Workload (2026-11-13)

This is a deliberately small synthetic workload, not production representative.
It ran twice at HEAD `b4056f5787a5538a12cf6669880910b4b0df1f89` with a dirty,
actively changing worktree: SDK builder, publisher stack and classifiers were
being edited concurrently. The checkout was not frozen; rerun against the final
integrated tree before interpreting its results. PHP 8.5.12 CLI NTS on the
unisolated, unpinned WSL2 host described below used CLI OPcache, JIT and PCOV
disabled. Runs were sequential, with no warmup and other development activity.

Each run creates one SDK with the current builder's default publisher stack and
redactor, required real clock, serializer and allocator, a counting sink and a
custom sequential business-ID generator. It executes 100 synchronous operations,
each with one completed activity and one counter, info observation, occurrence
and explicit snapshot inside that activity. It also emits execution and activity
lifecycle publications and a terminal snapshot. Expected counts below are
publication attempts implied by this fixture, not counts of unique objects.
Counts classify sink outputs by projection kind or concrete domain class, and
do not prove downstream export or complete field retention.

| Artifact | Expected | Sink, each run | Difference |
| --- | ---: | ---: | ---: |
| Execution lifecycle | 200 | 200 | 0 |
| Activity lifecycle | 200 | 200 | 0 |
| Counter measurement | 100 | 0 | 100 |
| Observation | 100 | 100 | 0 |
| Occurrence | 100 | 100 | 0 |
| Explicit snapshot | 100 | 100 | 0 |
| Terminal snapshot | 100 | 100 | 0 |

Both runs reported 100 health notices, each `Default measurement classification
dropped an unclassified measurement.` This correlates with the missing counters,
but does not quantify provenance-only drops. The full SDK loops took 112.414 and
108.141 ms, respectively, including creation, publication and cleanup, not SDK
construction. There is no controlled no-provenance baseline. The custom generator
exercises SDK-minted export IDs independently of business IDs; imported or forged
artifacts, unknown IDs, asynchronous retention, expiration and capacity loss
were not exercised through the SDK.

The separate authority-only fixture creates one execution binding and 10,000
observation bindings with execution-observation edges, using default authority
limits. It measures `memory_get_usage(false)` after constructing the empty
authority, then after issuance and after release, without creating or attesting
artifact objects. Both runs retained 10,001 records and verified zero after
release. Live PHP-accounted memory increased by 19,388,104 bytes and the
post-release delta was 1,967,000 bytes in both runs. Issuance took 44.229 and
43.980 ms; release took 11.195 and 10.923 ms. These are total allocator-accounted
deltas and elapsed wall times, including random export-ID generation and indexes,
not isolated per-record costs, RSS, peak memory, full SDK retention costs or
proof of a leak. The two fixtures are distinct and cannot be subtracted.

Reproduce from the package directory with the following complete PHP program,
using the flags shown after it. The checks throw on authority retention mismatch.
Record the actual source state and rerun counts: uncommitted changes can alter
both the behavior and results.

```php
<?php
declare(strict_types=1);
require getcwd() . '/vendor/autoload.php';

use ItPack\RunIt\Adapter\Common\{InMemorySequenceAllocator,JsonSnapshotSerializer,SystemMonotonicClock};
use ItPack\RunIt\Application\Publication\ProvenanceAuthority;
use ItPack\RunIt\Contract\Domain\{ExecutionArtifactInterface,RedactedArtifactInterface};
use ItPack\RunIt\Contract\Port\{ArtifactPublisherInterface,IdentifierGeneratorInterface};
use ItPack\RunIt\Domain\Activity\{ActivityDefinition,ActivityKind,ActivityName};
use ItPack\RunIt\Domain\Execution\{ExecutionDefinition,ExecutionKind,ExecutionName};
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Snapshot\SnapshotType;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

$sink = new class implements ArtifactPublisherInterface {
    public array $counts = [];
    public function publish(ExecutionArtifactInterface $artifact): void {
        $kind = $artifact instanceof RedactedArtifactInterface ? $artifact->artifactType() : get_class($artifact);
        $this->counts[$kind] = ($this->counts[$kind] ?? 0) + 1;
    }
};
$generator = new class implements IdentifierGeneratorInterface {
    private int $n = 0;
    public function generate(): string { return 'synthetic-' . ++$this->n; }
};
$health = [];
$sdk = RunItBuilder::create()->withPublisher($sink)->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator($generator)->withPublicationFailureHandler(static function (Throwable $e) use (&$health): void {
        $health[$e->getMessage()] = ($health[$e->getMessage()] ?? 0) + 1;
    })->build();
$definition = ExecutionDefinition::create(ExecutionName::fromString('synthetic'), ExecutionKind::fromString('task'));
$activity = ActivityDefinition::create(ActivityName::fromString('child'), ActivityKind::fromString('task'));
$start = hrtime(true);
for ($i = 0; $i < 100; ++$i) {
    $sdk->run($definition, static function ($scope) use ($activity): void {
        $scope->activities()->run($activity, static function ($s): void {
            $s->measurements()->counter('synthetic.count', 1);
            $s->observations()->record('synthetic', ObservationSeverity::Info);
            $s->occurrences()->record(OccurrenceType::fromString('synthetic.event.v1'), 'synthetic', ['value' => 1]);
            $s->snapshots()->record(SnapshotType::fromString('synthetic.snapshot'), ['value' => 1]);
        });
    });
}
printf("SDK ms=%.3f health=%s counts=%s\n", (hrtime(true)-$start)/1e6, json_encode($health), json_encode($sink->counts));

gc_collect_cycles();
$a = new ProvenanceAuthority();
$base = memory_get_usage(false);
$t = hrtime(true);
$a->issueExportId('execution', 'execution', 'exec', 'exec');
for ($i = 0; $i < 10000; ++$i) {
    if ($a->bindArtifact('observation', 'observation', 'exec', 'obs-'.$i, 'execution-observation') === null) {
        throw new RuntimeException('binding failed');
    }
}
$made = hrtime(true);
$live = memory_get_usage(false);
$count = count($a->allRecords());
$a->releaseExecution('exec');
$released = hrtime(true);
$after = memory_get_usage(false);
if ($count !== 10001 || count($a->allRecords()) !== 0) { throw new RuntimeException('retention check failed'); }
printf("Authority records=%d live_delta=%d release_delta=%d issue_ms=%.3f release_ms=%.3f\n",
    $count, $live-$base, $after-$base, ($made-$t)/1e6, ($released-$made)/1e6);
```

Run the program from this directory with
`php -d opcache.enable_cli=0 -d opcache.jit=disable -d pcov.enabled=0 <program.php>`.
The embedded program differs from the executed temporary harness only in
whitespace. It uses internal authority APIs and is tied to the measured checkout.
No real exporter, concurrency, failure injection, import mix, expiry, distribution
or sustained workload was measured. The Phase 2 pre-adoption requirement for
representative delivery-loss and retention-cost evidence remains **unmet**;
these numbers are neither production loss rates nor regression thresholds.

## Phase 2 Delivery And Retention Assessment (2026-11-13)

The Phase 2 pre-adoption decision requires delivery-loss and retention-cost
measurements on representative workloads for the proposed SDK-issued identity
and provenance mechanism. The existing buffer benchmarks do not exercise that
mechanism. Their controlled losses are buffer-policy outcomes, not estimates of
unknown-identity or provenance-expiry drops. No representative workload or
provenance retention memory, CPU, or lifetime cost is established here; this
requirement remains unmet.

At clean starting revision `b4056f5787a5538a12cf6669880910b4b0df1f89`, run
sequentially from the package directory:

```bash
php -d opcache.enable_cli=0 -d opcache.jit=disable -d pcov.enabled=0 vendor/bin/phpbench run benchmarks/BufferedFlushBench.php --config=phpbench.json --report=aggregate
php -d opcache.enable_cli=0 -d opcache.jit=disable -d pcov.enabled=0 vendor/bin/phpbench run benchmarks/BufferedOverflowBench.php --config=phpbench.json --report=aggregate
```

PHPBench 1.7.0 used PHP 8.5.12 CLI NTS without Xdebug or CLI OPcache, on the
unisolated, unpinned x86-64 WSL2 host described below. Each subject ran ten
one-revolution iterations with no warmups. Both commands exited successfully;
verification hooks passed, although PHPBench reported zero assertions.
Modes are microseconds per operation or batch, with relative standard deviations:

| Existing synthetic workload | Mode | Relative standard deviation |
| --- | ---: | ---: |
| Flush one 256-item batch | 653.726 | 2.74% |
| Close, full 1,024-item budget | 1,747.587 | 4.06% |
| Four 256-item flushes | 1,718.004 | 2.32% |
| Close, 256-item budget | 1,533.479 | 7.33% |
| Count-bound overflow, 128 publishes | 984.476 | 6.11% |
| Byte-bound overflow, 128 publishes | 988.033 | 1.63% |

The close fixture pre-fills 1,024 distinct small snapshots outside timing with
the real byte estimator, an 8 MiB byte limit, and an in-memory sink. Full close
and repeated flush deliver all 1,024 with zero drops. Limited close delivers the
first 256 and drops 768 of 1,024 (75%); its hook checks FIFO, drops and empty
accounting. One flush delivers 256 and retains 768 until an untimed close.
Overflow uses two reused equal-estimated-size snapshots, a full 1,024-item buffer,
and 128 incoming publishes with `DropOldest`. The hooks check 128 evictions,
1,024 survivors in order and their eventual delivery: 128 of 1,152 offered
artifacts are dropped in each artificial batch (about 11.1%). Setup and the
overflow verification close are outside timing. Neither scenario uses a real
exporter or measures downstream delivery failure.

PHPBench's reported process peaks (10.497 MB for close and 8.971 MB for
overflow) include runtime and fixtures, not incremental buffer or provenance
retention costs. These fixed-order synthetic samples have no controlled repeat,
production traffic distribution, concurrency, real I/O, expiration, failure
injection, or memory profiling. They are not production loss rates, throughput,
regression thresholds, or evidence for adopting the proposed mechanism. Closing
the Phase 2 gap requires representative artifact and identity provenance mixes,
including unknown IDs and asynchronous retention/expiry under bounded capacity,
with measured delivery outcomes and incremental memory and CPU costs, plus the
separate migration and compatibility review specified in the plan.

## Phase 2 Partial Baseline (2026-11-13)

At starting revision `2944e703f36e9f64272d99a5454bca5d1011b7fe`, the
worktree had only an untracked root `graphify-out/` directory. PHPBench 1.7.0
ran PHP 8.5.12 CLI NTS on x86-64 WSL2 (Linux 5.10.16.3), a Microsoft
hypervisor guest with four virtual CPUs on an Intel Core i7-9700. The default
PHP has PCOV, OpenTelemetry and Swoole extensions loaded; Xdebug is absent.
CLI OPcache, JIT and PCOV were disabled by the commands. The host was unpinned
and not isolated; no other benchmark was deliberately run concurrently.
An unrelated modification to
`tests/Unit/Application/Publication/RedactionSecurityCharacterizationTest.php`
appeared during this work, so the worktree was not immutable throughout the run;
the benchmark subjects do not use that test file.

From the package directory, run these three commands sequentially, then repeat
the same sequence once:

```bash
php -d opcache.enable_cli=0 -d opcache.jit=disable -d pcov.enabled=0 vendor/bin/phpbench run benchmarks/NestedSnapshotBench.php --config=phpbench.json --report=aggregate
php -d opcache.enable_cli=0 -d opcache.jit=disable -d pcov.enabled=0 vendor/bin/phpbench run benchmarks/SampledOutBench.php --config=phpbench.json --report=aggregate
php -d opcache.enable_cli=0 -d opcache.jit=disable -d pcov.enabled=0 vendor/bin/phpbench run benchmarks/ExecutionBench.php --config=phpbench.json --filter=benchRedaction --report=aggregate
```

All six invocations exited 0, reporting no errors or failures and zero PHPBench
assertions. Values are PHPBench modes in microseconds per operation, or per
128-publication batch for the sampled-out case; parentheses show relative
standard deviation within that run, not a confidence interval.

| Workload | First run | Repeat |
| --- | ---: | ---: |
| Snapshot projection, 8 rows | 1.388 (3.86%) | 1.377 (1.40%) |
| Snapshot projection, 64 rows | 1.385 (2.73%) | 1.407 (5.29%) |
| Configured redaction, 8 rows | 43.813 (5.61%) | 43.801 (9.27%) |
| Configured redaction, 64 rows | 258.772 (4.07%) | 274.499 (1.93%) |
| JSON normalization, 8 rows | 47.106 (2.03%) | 47.973 (5.19%) |
| JSON normalization, 64 rows | 346.947 (0.20%) | 356.929 (3.30%) |
| Redacting publisher, 8 rows | 50.428 (3.24%) | 52.561 (3.44%) |
| Redacting publisher, 64 rows | 299.868 (3.87%) | 287.522 (4.77%) |
| Sampled-out, 128 publications | 38.315 (9.79%) | 39.507 (11.62%) |
| Always-sampled small snapshot chain | 10.397 (2.59%) | 10.727 (7.30%) |

Nested snapshot subjects use five iterations of 100 revolutions after two
warmups, prebuilt 8- or 64-row payloads and two sensitive keys per row. Setup
checks masking, source immutability, normalization and the JSON-encoding
in-memory sink; it does not verify each timed output. Projection is not deep
serialization. The sampled-out subject uses ten one-revolution iterations,
without warmup, each publishing the same snapshot 128 times directly through
`NeverSamplePolicy` without a redactor or health reporting; its hooks check
the empty sink and an always-sampled positive control. The small chain uses
five iterations of 100 revolutions after two warmups, a prebuilt two-field
snapshot, `AlwaysSamplePolicy`, default redactor and in-memory sink, in that
sampling-before-redaction order; it has no timed-output verification. Setup is
outside timing. These are separate workloads, not an isolated cost comparison.

The repeated 64-row redaction modes differ by about 6%; only two runs and a
small iteration count do not characterize host variability. PHPBench process
peaks include the runtime and fixture and are not per-artifact memory profiles.
No PHP 8.4 or 8.6 measurement was made. Mixed artifact traffic, failed execution
and nested throwable redaction, adapter routing and health-reporting failure
paths, real exporter I/O, concurrency, dedicated memory profiles and the planned
SDK-owned export-ID issuance and provenance retention have no measurement in
these runs. In particular, the sampling-only rejection benchmark cannot predict
the cost of redaction-before-sampling. These synthetic results are not production
throughput, regression thresholds or controlled before/after comparisons, and do
not establish Phase 2 performance coverage or completion.

## Phase 0 Matrix (2026-11-11)

This inventory distinguishes durable workloads from historical temporary harnesses;
their different fixtures and methods do not support direct comparisons.

| Matrix cell | Evidence | Remaining gap |
| --- | --- | --- |
| Synchronous attach/detach and lookup | `ContextStorageBench`, stack and composite, depths 0 and 63 | No coroutine detector or realistic scheduling |
| Fiber attach/detach | `FiberContextStorageBench`, two interleaved Fibers at depths 1 and 63 | Switches included; other scheduling not covered |
| Fiber lookup | `tools/runtime-context-benchmark.php`; `FiberContextStorageBench` interleaved at depths 1 and 63 | Checks only the last lookup in each Fiber |
| Swoole attach/detach and lookup | `tools/runtime-context-benchmark.php`, depths 0 and 63 | No interleaving, OpenSwoole or Swow measurement |
| Buffer admission | `BufferedAdmissionBench`, two nested snapshot sizes, real estimator | No concurrent traffic |
| Count and byte overflow | `BufferedOverflowBench`, equal-sized snapshots and real estimator; historical priority harness below | Priority cases not durable; limited strategies and fixture sizes; projected byte admission unmeasured |
| Drain and close | `BufferedFlushBench` times single and repeated flush, full- and limited-budget close | Synthetic in-memory sink, no concurrent traffic |
| Publication, sampling and redaction | `ExecutionBench::benchRedaction`; `NestedSnapshotBench`; `SampledOutBench` | No representative mixed traffic |
| Serialization, nested artifacts | `NestedSnapshotBench` and `CloudEventBench` | No real export I/O or broad artifact distribution |

Memory profiling and real traffic are unmeasured for this matrix. Historical
temporary scripts are not guaranteed to exist; missing durable cases remain open.
Phase 0 accepts this documented baseline with its gaps; missing measurements
remain follow-ups and must not be presented as completed or representative.
Later statements about Phase 0 remaining open describe the historical status at
the time of those measurements and are superseded by the refactoring plan.

### Additional durable workloads (2026-11-11)

At revision `630c14ca2447548108e585439c9780c222a0c80f` with benchmark changes,
PHPBench 1.7.0 ran on PHP 8.5.12 CLI NTS, x86-64 WSL2 with four virtual CPUs.
CLI OPcache, JIT and PCOV were disabled; Xdebug was absent. Run from this package:

```bash
php -d opcache.enable_cli=0 -d opcache.jit=disable -d pcov.enabled=0 vendor/bin/phpbench run benchmarks/BufferedFlushBench.php --config=phpbench.json --report=aggregate
php -d opcache.enable_cli=0 -d opcache.jit=disable -d pcov.enabled=0 vendor/bin/phpbench run benchmarks/FiberContextStorageBench.php --config=phpbench.json --report=aggregate
php -d opcache.enable_cli=0 -d opcache.jit=disable -d pcov.enabled=0 vendor/bin/phpbench run benchmarks/SampledOutBench.php --config=phpbench.json --report=aggregate
```

| Workload | Mode | Relative standard deviation |
| --- | ---: | ---: |
| Four 256-item flushes | 2,828.501 µs/batch | 70.62% |
| Limited-budget close, 1,024 items | 1,918.221 µs/close | 14.80% |
| Two interleaved Fiber lookups, depth 1 | 2.262 µs/pair | 34.67% |
| Two interleaved Fiber lookups, depth 63 | 2.635 µs/pair | 34.33% |
| 128 sampled-out publications | 48.182 µs/batch | 10.51% |

Buffer and sampled-out workloads used ten one-revolution iterations without
warmups; Fiber used ten iterations of 10,000 revolutions after two warmups.
Untimed checks verify delivery, drops and accounting, Fiber cleanup and the last
lookup, and sampled-out sink behavior with a sampled-in positive control. The
shared host was unpinned and unisolated; the sampled-out repeat overlapped other
checks. Approximate process peaks were 10.5, 9.0 and 8.5 MB respectively, not
per-operation memory profiles. These synthetic, variable measurements are not
thresholds, production throughput or controlled comparisons. Durable mixed-size
priority and projected-byte overflow, representative mixed publication, real
export I/O and additional runtime adapters remain unmeasured; Phase 0 stays open.

### Full-budget close measurement (2026-11-11)

At clean starting revision `22f51cfd17fed90f1a78af9d87b3662c8928a4ce`,
with the benchmark change in the worktree, run from the package directory:

```bash
php -d opcache.enable_cli=0 -d opcache.jit=disable -d pcov.enabled=0 vendor/bin/phpbench run benchmarks/BufferedFlushBench.php --config=phpbench.json --report=aggregate
```

PHPBench 1.7.0 used PHP 8.5.12 CLI NTS, with Xdebug absent and CLI OPcache,
JIT and PCOV disabled, on an x86-64 Microsoft-hypervisor guest with four virtual
CPUs on an Intel Core i7-9700. The existing fixture pre-fills 1,024 distinct small
snapshots using the real byte estimator, a system clock, 8 MiB byte limit,
256-item batch and in-memory sink; setup is outside timing. The close subject
times one `close()` with a 1,024-item shutdown budget. Its verification hook
checks exact FIFO delivery, zero buffered items and bytes, and zero drops.
The flush subject times one batch and checks its remaining accounting before an
untimed close. Ten iterations with one revolution and no warmups ran in fixed
subject order; both hooks passed, although PHPBench reports zero assertions.

| Operation | Mode per operation | Relative standard deviation |
| --- | ---: | ---: |
| Flush 256 items | 808.777 µs | 166.10% |
| Full-budget close, 1,024 items | 2,117.933 µs | 42.17% |

The shared host was not pinned or isolated. These highly variable synthetic
measurements include clock and in-memory delivery, not actual export I/O, and
exclude admission, overflow, expiration, concurrency, failure paths, memory
profiling and limited-budget close. They are not thresholds, production
throughput or controlled comparisons with historical temporary workloads. The
broader benchmark matrix and Phase 0 remain open.

### Durable overflow and runtime workloads (2026-11-11)

At revision `f1debe4a61aa15f0ba755dbbde64ff3ab42b8918`, with pre-existing
documentation and generated test-support changes and the new workloads, PHP
8.5.12 CLI NTS and PHPBench 1.7.0 ran on the unpinned x86-64 Microsoft-hypervisor
guest described below. CLI OPcache, JIT and PCOV were disabled; Xdebug was absent.
Swoole 6.2.3 was loaded. Commands, run sequentially:

```bash
php -d opcache.enable_cli=0 -d opcache.jit=disable -d pcov.enabled=0 vendor/bin/phpbench run benchmarks/BufferedOverflowBench.php --config=phpbench.json --report=aggregate
php -d opcache.enable_cli=0 -d opcache.jit=disable -d pcov.enabled=0 tools/runtime-context-benchmark.php
```

Each overflow iteration pre-fills 1,024 equal-estimated-size snapshots with the
real estimator, then times 128 `DropOldest` publishes. For count overflow the
item limit is 1,024 and byte limit twice the filled size; for byte overflow the
item limit is 2,048 and byte limit exactly the filled size. The 3,600-second age,
disabled periodic flush, real clock, in-memory sink and 1,024-item shutdown budget
are identical. Setup, accounting checks and close are outside timing; checks
verify 128 drops, retained count and bytes, exact survivor order and empty
buffer after close. Two distinct reused snapshots have equal estimated sizes.
Ten iterations use one batch per revolution and zero warmups; values are modes
in microseconds **per 128-publish batch**, with relative standard deviation:

| Trigger | First run | Repeat run |
| --- | ---: | ---: |
| Count | 1,224.233 (16.53%) | 1,124.450 (9.65%) |
| Bytes | 1,318.041 (131.45%) | 1,210.945 (136.48%) |

All verification hooks passed, though PHPBench reports zero assertions. The
first attempted run rejected unequal fixture sizes before timing; the fixture was
corrected before the measurements above. A separate PHP 8.4.27 run passed the
same hooks, with modes of 1,482.200 microseconds per count batch (46.21% relative
standard deviation) and 1,406.407 per byte batch (148.08%). These results are not comparable to the
historical constant-estimator harnesses, nor do they demonstrate enforcement of
projected byte admission with differing artifact sizes. The high variability,
especially in the byte case, rules out regression thresholds.

The standalone runtime harness runs inside one Fiber, then one Swoole or
OpenSwoole coroutine. It times 10,000 lookups or attach/detach pairs per sample,
after two warmup samples, with ten recorded samples at each depth. Scope
construction, parent attachments, last-result checks and LIFO cleanup are
outside timing. It verifies the active coroutine ID, restored scope and empty
storage, but does not check every lookup or exercise interleaving. Missing
runtimes emit `SKIP` rather than synthetic timing; `php -n` verified the
coroutine skip. A PHP 8.4.27 invocation also completed with Swoole; OpenSwoole
and Swow were not available for verification. The PHP 8.5 run reported median
and full range in microseconds per operation:

| Runtime | Operation | Depth 0 | Depth 63 |
| --- | --- | ---: | ---: |
| Fiber | Lookup | 0.249 (0.234-0.472) | 1.054 (0.487-1.564) |
| Fiber | Attach/detach | 2.065 (0.953-2.990) | 2.683 (1.767-4.744) |
| Swoole | Lookup | 0.434 (0.384-2.141) | 1.103 (0.672-1.435) |
| Swoole | Attach/detach | 1.858 (1.597-4.256) | 4.104 (2.337-5.439) |

These fixed-order, unpinned synthetic samples include loop overhead and have
large outliers. They exclude scheduling, concurrency, real I/O, failure paths
and memory profiling. They do not establish thresholds, production throughput,
or controlled comparisons with the earlier temporary or other-version runs.

### Current representative measurements

At revision `f1debe4a61aa15f0ba755dbbde64ff3ab42b8918`, initially clean,
PHP 8.5.12 CLI NTS, PHPBench 1.7.0, Swoole 6.2.3, x86-64 Microsoft-hypervisor
guest, four virtual CPUs on Intel Core i7-9700; CLI OPcache, JIT and PCOV were
disabled and Xdebug absent. The host was unpinned and not isolated. Two PHPBench
commands ran concurrently, so their results are especially unsuitable for
thresholds or comparison:

```bash
php -d opcache.enable_cli=0 -d opcache.jit=disable -d pcov.enabled=0 vendor/bin/phpbench run benchmarks/BufferedFlushBench.php --config=phpbench.json --report=aggregate
php -d opcache.enable_cli=0 -d opcache.jit=disable -d pcov.enabled=0 vendor/bin/phpbench run benchmarks/ExecutionBench.php --config=phpbench.json --filter=benchRedaction --report=aggregate
```

The flush mode was 738.822 microseconds per 256-item batch, relative standard
deviation 21.86%, ten iterations, one revolution and no warmups. Its fresh
1,024-item buffer, real estimator, 8 MiB limit, prefill and post-flush FIFO,
accounting and close checks are described in the earlier flush baseline below;
setup and close are outside timing. The sampling-to-redaction publication mode
was 4.346 microseconds per snapshot, relative standard deviation 63.67%, five
iterations of 100 revolutions after two warmups. It uses `AlwaysSamplePolicy`,
the default redactor and an in-memory sink, with a prebuilt two-field snapshot;
setup is outside timing and there is no timed-output verification. Both commands
completed without errors; neither result establishes a performance threshold.

### Temporary runtime context workloads

`/tmp/kilo/runit-phase0-context.php` (SHA-256
`6bbd8462ac5be101d550b66c5934df510e1e0c4f921aad9268c8b4e350bfc7eb`)
is a temporary, untracked harness, not a durable suite addition. While present:

```bash
php -d opcache.enable_cli=0 -d opcache.jit=disable -d pcov.enabled=0 /tmp/kilo/runit-phase0-context.php
```

It runs one Fiber and one Swoole coroutine sequentially with real storage and a
reused real scope. At depths 0 and 63, it times either `current()` plus result
assignment or an immediate attach/detach pair. Per case there are two warmup
rounds and ten samples of 10,000 operations. Construction and parent attachment,
the last lookup result check and LIFO cleanup are outside timing. Cleanup checks
empty storage; it does not verify every timed lookup or exercise interleaving.
The second invocation overlapped extension inspection and hashing. Median and
full sample range in microseconds per operation, first and repeat invocation:

| Runtime and operation | Depth | First median (range) | Repeat median (range) |
| --- | ---: | ---: | ---: |
| Fiber lookup | 0 | 0.222 (0.205-1.213) | 0.234 (0.225-0.265) |
| Fiber pair | 0 | 1.085 (0.941-1.954) | 2.062 (1.005-3.616) |
| Fiber lookup | 63 | 0.547 (0.494-0.564) | 0.545 (0.525-2.484) |
| Fiber pair | 63 | 1.610 (1.533-4.274) | 1.917 (1.571-2.565) |
| Swoole lookup | 0 | 0.388 (0.358-0.546) | 0.466 (0.373-2.372) |
| Swoole pair | 0 | 1.693 (1.411-2.452) | 1.904 (1.492-3.426) |
| Swoole lookup | 63 | 0.727 (0.669-2.765) | 0.830 (0.714-1.056) |
| Swoole pair | 63 | 3.622 (2.243-8.152) | 4.066 (2.968-5.303) |

Both runs passed their checks. Fixed case order, scheduler noise, loop and result
assignment costs, and substantial outliers preclude thresholds or controlled
runtime/depth comparisons. Scope construction, coroutine switches, concurrency,
failure paths and memory profiling are excluded. OpenSwoole and Swow were not
loaded in the measured PHP runtime. These are not production throughput figures.

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

## Synchronous context lookup baseline (2026-11-11)

At clean starting revision `135c0e703774067060a16cfd610a64ba54e49a9d`, plus
the benchmark and documentation changes, run sequentially from the package
directory:

```bash
php8.5 -d opcache.enable_cli=0 -d opcache.jit=disable -d pcov.enabled=0 vendor/bin/phpbench run benchmarks/ContextStorageBench.php --config=phpbench.json --filter='Current' --report=aggregate
php8.4 -d opcache.enable_cli=0 -d opcache.jit=disable -d pcov.enabled=0 vendor/bin/phpbench run benchmarks/ContextStorageBench.php --config=phpbench.json --filter='Current' --report=aggregate
```

Each operation calls `current()` and stores its result, on a direct stack or a
composite using `FiberRuntimeDetector` and a stack, with zero or 63 pre-attached
scopes. Each nested entry uses the same real scope. Setup, the final lookup
result check and LIFO cleanup are outside timing. Cleanup checks the current scope
and then emptiness; hooks throw on failure even though PHPBench reports zero
framework assertions. Each subject uses two warmups and ten iterations of 10,000
lookups. All four subjects completed on both versions without errors.

PHPBench 1.7.0, PHP CLI NTS, x86-64 Microsoft-hypervisor guest, four virtual
CPUs on Intel Core i7-9700; CLI OPcache, JIT and PCOV disabled and Xdebug absent.
Values are modes in microseconds per lookup, with relative standard deviations:

| Workload | PHP 8.5.12 | PHP 8.4.27 |
| --- | ---: | ---: |
| Stack, depth 0 | 0.323709 (135.11%) | 0.311564 (5.91%) |
| Stack, depth 63 | 0.442937 (68.16%) | 0.348178 (65.97%) |
| Composite, depth 0 | 0.821923 (50.22%) | 0.722870 (40.55%) |
| Composite, depth 63 | 0.747100 (14.38%) | 0.795883 (59.89%) |

The host was unpinned and not isolated; subjects ran in fixed order. These short
operations include benchmark dispatch and result assignment, and the last result
alone is checked. The composite detector does not check coroutine runtime state.
The workload excludes attach/detach timing, Fiber or coroutine execution,
interleaving, scope construction, failures, real traffic and memory profiling.
Process peaks are not per-lookup memory costs. High variance precludes using these
results as regression thresholds or as a controlled version or depth comparison;
they are not production throughput. Phase 0 remains open.

## Interleaved Fiber context storage baseline (2026-11-10)

At clean starting revision `f5c960515f998162f6b86460b25ea6dd6f332554`, plus
the new benchmark and documentation, run from the package directory, separately
for each installed PHP version:

```bash
php8.5 -d opcache.enable_cli=0 -d opcache.jit=disable -d pcov.enabled=0 vendor/bin/phpbench run benchmarks/FiberContextStorageBench.php --config=phpbench.json --report=aggregate
php8.4 -d opcache.enable_cli=0 -d opcache.jit=disable -d pcov.enabled=0 vendor/bin/phpbench run benchmarks/FiberContextStorageBench.php --config=phpbench.json --report=aggregate
```

Two persistent Fibers share a real `FiberContextStorage`, each with a distinct
reused real scope and 1 or 63 pre-attached parent tokens. One timed revolution
resumes each Fiber in turn; each attaches and detaches its own scope and suspends.
Thus the unit is **two pairs and two resume/suspend switches**, not one pair.
Construction and parent setup are outside timing. Initial checks reject inherited
context and main-stack leakage; after each iteration, each Fiber verifies its
own restored scope, detaches its parents in LIFO order and checks empty, while
the main stack is also checked. The first Fiber is checked while the second is
still suspended. Hooks throw on failure despite zero PHPBench assertions. Two
warmups precede ten iterations of 10,000 revolutions per subject.

PHPBench 1.7.0, CLI NTS, WSL2 x86-64, four virtual CPUs on Intel Core i7-9700;
CLI OPcache, JIT and PCOV disabled, Xdebug absent. Runs were sequential and no
other checks were deliberately run concurrently; the host was not isolated or
CPU-pinned. Modes and relative standard deviations per revolution:

| Existing depth per Fiber | PHP 8.5.12 | PHP 8.4.27 |
| --- | ---: | ---: |
| 1 | 3.770740 µs (3.57%) | 3.748208 µs (15.04%) |
| 63 | 4.690875 µs (8.46%) | 4.648044 µs (3.38%) |

This fixed-order synthetic workload includes Fiber switching and loop machinery;
it does not isolate storage cost or reproduce the earlier temporary Fiber harness.
Parent scopes remain attached across switches, but the timed child scope does not.
It excludes scope creation, timed `current()`, new Fiber creation, realistic
scheduling and I/O, composite and coroutine storage, failures, other concurrency
levels and memory profiling. PHPBench's process peak is not a storage memory
measurement. These unpinned measurements are not a controlled version or
before/after comparison, production throughput, or regression thresholds.
Phase 0 remains open.

## Synchronous context storage baseline (2026-11-10)

At starting revision `a83eab95ab22172f4a8d8e56a937897e141c88e2`, with
pre-existing plan edits and the new benchmark, run from the package directory:

```bash
php -d opcache.enable_cli=0 -d opcache.jit=disable -d pcov.enabled=0 vendor/bin/phpbench run benchmarks/ContextStorageBench.php --config=phpbench.json --report=aggregate
```

Each timed operation attaches a reused real execution scope and immediately
detaches its token, either directly through `StackContextStorage` or through
`CompositeContextStorage` delegating to a stack with `FiberRuntimeDetector`.
Outside timing, a fresh storage is prepared at depth 0 or 63, and after each
iteration the current scope and LIFO cleanup to empty are checked; failures throw
despite PHPBench reporting zero framework assertions. Scope creation and parent
attachments, `current()` and cleanup are excluded. Each subject has two warmups
and ten iterations of 10,000 pairs.

PHPBench 1.7.0 on PHP 8.5.12 CLI NTS, WSL2 x86-64, four virtual CPUs on an
Intel Core i7-9700, with CLI OPcache, JIT and PCOV disabled and Xdebug absent:

| Workload | Mode (µs/pair) | Relative standard deviation |
| --- | ---: | ---: |
| Stack, depth 0 | 1.115 | 18.78% |
| Stack, depth 63 | 1.097 | 5.06% |
| Composite, depth 0 | 2.289 | 3.65% |
| Composite, depth 63 | 2.267 | 3.55% |

A separate PHP 8.4.27 invocation with the same flags and workload passed the
checks, reporting modes of 1.130, 1.120, 2.283 and 2.336 µs/pair respectively,
with relative standard deviations of 15.83%, 3.33%, 2.31% and 42.65%. It ran
concurrently with graph extraction and is not a controlled PHP-version comparison.

This unpinned synthetic run uses sequential cases, not a controlled comparison;
other machine activity may affect it. The detector does not check coroutine
runtime state. It excludes Fiber execution and interleaving, coroutine storage,
scope construction, current-scope lookup timing, failures and memory profiling.
It is not production throughput or a regression threshold and
does not reproduce the earlier temporary Fiber harness. Phase 0 remains open.

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

#### PHP 8.4 snapshot measurement (2026-11-11)

At clean starting revision `6129e47c`, the same benchmark ran with PHP 8.4.27
CLI and PHPBench 1.7.0 on a Microsoft-hypervisor x86-64 guest with four virtual
CPUs on an Intel Core i7-9700. CLI OPcache, JIT and PCOV were disabled; Xdebug
was absent. The command above was run with `php8.4` in place of `php`. Each
subject used two warmups and five iterations of 100 operations; all eight
subjects completed without errors. Values are modes in microseconds per
operation, with relative standard deviations in parentheses.

| Workload | 8 rows | 64 rows |
| --- | ---: | ---: |
| Snapshot projection | 1.497 (8.54%) | 1.358 (7.11%) |
| Configured redaction | 42.632 (70.78%) | 238.531 (38.71%) |
| JSON normalization | 66.778 (13.94%) | 556.602 (10.31%) |
| Redacting publisher | 39.542 (60.46%) | 240.389 (42.44%) |

Execution was unpinned and other investigation was in progress. The especially
high redaction and publication variance makes these results unsuitable for a
regression threshold or a controlled comparison with the earlier PHP 8.5 run.
The synthetic workload uses an in-memory sink, no buffer, and excludes real I/O,
concurrency, failure paths, creation and hashing; reported process peaks are not
per-operation memory measurements. Phase 0 remains open.

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
