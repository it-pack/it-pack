# RunIt dogfooding

RunIt uses its own execution model to represent its own development tooling. This
is **dogfooding**: the project's tests, static analysis, architecture checks,
mutation testing, benchmarks, and release validation each run as a RunIt
execution, with their outcome recorded as measurements, an occurrence, and a
terminal snapshot.

Milestone **M14.6** covers this. The implementation lives under `tools/dogfood/`
(dev-only, autoloaded via `Tools\Dogfood\`) and is wired through `composer dogfood`.

## Why dogfood at all

The execution model already distinguishes an execution from its activities,
measurements, observations, occurrences, and snapshots. The project's own CI is
just another set of executions. Representing it as such exercises the public API
the same way a third party would, and it gives the tooling runs a stable,
queryable shape (`runit.dogfood.test.completed.v1`, a `runit.dogfood.test.summary`
snapshot, and so on).

## The represented runs

`Tools\Dogfood\Tool\PredefinedTools` maps each tool to a composer script:

| Tool                 | Command             | Occurrence type base              |
|----------------------|---------------------|-----------------------------------|
| Composer validation  | `composer validate --strict` | `runit.dogfood.composer_validate` |
| Rector dry-run       | `composer refactor:dry`      | `runit.dogfood.rector_dry`        |
| Style check          | `composer cs:check`          | `runit.dogfood.cs_fixer_dry`      |
| Static analysis      | `composer analyse`           | `runit.dogfood.static_analysis`   |
| Architecture analysis| `composer architecture`      | `runit.dogfood.architecture`      |
| API check            | `composer api:check`         | `runit.dogfood.api_check`         |
| Line-length lint     | `composer lint:length`       | `runit.dogfood.line_length_lint`  |
| Test execution       | `composer test`              | `runit.dogfood.test`              |
| Security audit       | `composer security:audit`    | `runit.dogfood.security_audit`    |
| Benchmark execution  | `composer benchmark`         | `runit.dogfood.benchmark`         |

For each tool the runner (`Tools\Dogfood\Run\DogfoodRunner`) opens an execution
through the SDK, runs the tool as a **subprocess**, and records:

- a `runit.dogfood.tool.duration` measurement (milliseconds, monotonic clock);
- a `runit.dogfood.tool.exit_code` measurement (count);
- a `runit.dogfood.tool.stdout_bytes` measurement (bytes);
- a `runit.dogfood.tool.stderr_bytes` measurement (bytes);
- a warning observation when the tool exits non-zero;
- an occurrence `runit.dogfood.<tool>.(completed|failed).v1`;
- a terminal snapshot `runit.dogfood.<tool>.summary` carrying the command, exit
  code, duration, stdout size, and a truncated stderr tail.

All four measurements pass through the SDK's default measurement classification
(`DefaultMeasurementClassifier`) before reaching the sink, so they are delivered
as validated, bounded `Measurement` projections rather than dropped.

A non-zero exit marks the dogfood execution as failed (via
`Tools\Dogfood\Exception\ToolFailedException`) and makes the report exit non-zero,
so the gate fails the way the underlying tool would.

## Instrumenting adapter batch flushes

The only place RunIt observes *itself* is the buffered publisher's batch flush.
`BufferedArtifactPublisher` now accepts a `BatchFlushObserverInterface` and calls
`onFlush(flushedCount, durationMicroseconds, remainingBuffered)` on every flush and
on close. `RecordingBatchFlushObserver` is the provided implementation: it keeps
counters only (flush count, total flushed, max batch, last duration, last
remaining). Because the observer never re-publishes artifacts, it cannot recurse
through the guarded pipeline.

The dogfooding entry wires a `RecordingBatchFlushObserver` into the buffered
publisher and prints the self-instrumentation totals after the run, so a dogfood
execution can report how many of RunIt's own artifacts its exporter batches
flushed while running the tool.

## Verify dogfooding does not recurse

The dogfooding publisher is a **leaf collector** (`InMemoryArtifactPublisher`)
optionally wrapped in a buffered publisher whose flush observer only increments
counters. Nothing in the dogfooding path re-enters the artifact pipeline, so the
`PublicationGuard` is never triggered. A unit test
(`tests/Unit/Tools/Dogfood/DogfoodRunnerTest.php`) asserts that artifact counts
scale linearly with the number of tools and stay bounded per tool — the signature
of a non-recursive representation.

## Verify dogfooding does not distort benchmarks

Each tool runs in a **subprocess** with its own bootstrap, so the measured tool is
a separate process from the dogfooding orchestrator. The dogfooding module is
therefore never loaded into the benchmark process:

- `tools/dogfood/run-dogfood.php` only requires `vendor/autoload.php` and the
  dogfooding classes; it never loads the benchmark suite.
- `benchmarks/` and `phpbench.json` do not import `Tools\Dogfood` (enforced by
  `tests/Integration/Tools/Dogfood/BenchmarkBootstrapUninstrumentedTest.php`).

The SDK builder installs no global telemetry providers, hooks, or process-wide
state, so merely constructing a `RunIt` instance does not change the behavior of
any other process.

## The intentionally uninstrumented bootstrap path

The dogfooding bootstrap is intentionally thin:

1. `composer dogfood` invokes `tools/dogfood/run-dogfood.php`.
2. The script builds a `RunIt` instance with a buffered publisher over an
   in-memory recorder and a counters-only flush observer, plus the standard
   default ports (`SystemMonotonicClock`, `InMemorySequenceAllocator`,
   `JsonSnapshotSerializer`, `RandomIdentifierGenerator`).
3. For each tool it opens an execution, runs the tool in a subprocess, and drains
   the publisher.
4. It prints a plain-text report and the flush self-instrumentation, then exits
   with the report's exit code.

No adapter (OpenTelemetry, CloudEvents, PSR bridges) is attached, so dogfooding
does not export anywhere by default — it demonstrates the model without becoming
a telemetry source itself. A consumer who wants dogfooding exported would attach a
real publisher and rely on the same `PublicationGuard` that protects every other
RunIt pipeline.

## Running it

```bash
composer dogfood
```

This runs the full project quality gate as dogfood executions. It is intended for
local and CI use where observing the tooling runs as executions is wanted; it is
deliberately not attached to the default `quality` script so the release gate
does not observe itself.
