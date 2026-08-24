# RunIt integration

DeriveIt aligns with the [It-Pack execution model](../EXECUTION.md):
**everything happens within an execution.** A derivation run is an execution;
the pipeline's stages are its activities; counts, notices, and completions are
its measurements, observations, and occurrences.

The integration is fully decoupled: DeriveIt emits facts through its own
dependency-free `Contract\Telemetry\DeriveEventSinkInterface`, and the sibling
package **`it-pack/derive-it-runit`** (directory `src/DeriveItRunit/`) maps
those facts onto a running execution. Neither package imports the other — the
bridge is the only meeting point, exactly like `ConfigureItPsr11` for
ConfigureIt.

## Installation

```bash
composer require it-pack/derive-it-runit
```

## Quickstart

```php
use ItPack\DeriveIt\DeriveIt;
use ItPack\DeriveIt\Generator\DeriveConfig;
use ItPack\DeriveItRunit\DeriveItExecutions;
use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

$publisher = new InMemoryArtifactPublisher(); // or any ArtifactPublisherInterface

$runtime = RunItBuilder::create()
    ->withPublisher($publisher)
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->build();

$source = DeriveItExecutions::run(
    $runtime,
    'derive.flat',
    fn ($sink) => DeriveIt::deriveWithResult($class, new DeriveConfig(), $sink)->source,
);
```

The per-trait, adapter, family, result, and drift-detection entries on the
`DeriveIt` facade each accept an optional trailing
`?DeriveEventSinkInterface $eventSink = null`; hand it the sink your closure
receives and every pipeline fact attaches to the ambient execution.

## Fact mapping

| DeriveIt fact | RunIt artifact |
|---|---|
| activity started/finished | occurrence `deriveit.activity.{started,finished}.v1` |
| measurement | `deriveit.<name>` (`count`→counter, `By`→size, `ms`→duration, `%`→ratio, else gauge) |
| observation | observation with mapped severity (`warning` → `Warning`, …) |
| occurrence | occurrence `deriveit.<type>.v1`, target FQCN as subject |

Vocabulary: execution kind **`derivation.run`**, names `derive.<mode>`
(`derive.flat`, `derive.family`, `derive.diff`, …), activities `graph.walk`,
`surface.extract`, `leakage.audit`, `diff.compute`.

## Recipe: CI drift jobs as executions

A drift check is a build-process execution (per `EXECUTION.md`). Wrap all
`diff()` calls of one CI job in a *single parent* derivation execution so the
job produces one correlated record instead of N loose derivations:

```php
$result = DeriveItExecutions::run(
    $runtime,
    'derive.drift-job',                       // one execution per CI job
    function ($sink) use ($targets): array {
        $report = [];
        foreach ($targets as $class => $committedSource) {
            $diff = DeriveIt::diff($class, $committedSource, new DeriveConfig(), $sink);
            $report[$class] = $diff->drift ? $diff->diff : null;
        }

        return $report;
    },
);

if (array_filter($result) !== []) {
    exit(1); // drift detected — occurrences carry the details
}
```

What lands on the execution:

- one nested lifecycle per checked class
  (`derivation.started` / `derivation.completed`),
- one `diff.compute` activity per class,
- a `drift.detected` or `drift.clean` occurrence per class — filter these to
  render the CI summary or feed an events pipeline,
- `source.bytes` + surface measurements for trend dashboards.

Failures still throw (fail-fast is preserved); wrap the job in your runtime's
error handling and RunIt normalizes the throwable onto the execution.

## CLI

`derive --verbose` streams the same facts to stderr as human-readable lines
(`[activity] … started`, `[measure] … = …`, `[!] warnings`) without touching
stdout. Useful as a zero-setup preview of what the bridge would record.
