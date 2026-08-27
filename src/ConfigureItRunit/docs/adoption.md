# ConfigureIt adoption case study (RunIt M14.7)

This document records how **ConfigureIt** (`it-pack/configure-it`) was adopted by the
RunIt execution model under milestone **M14.7**. The integration lives in a separate,
dependency-light package — `it-pack/configure-it-runit` — exactly like the existing
`it-pack/derive-it-runit` bridge. Neither `configure-it` nor `run-it` imports the other;
the bridge is the only meeting point.

## Goal

Model configuration resolution as one RunIt **execution** and record its artifacts:

- Activities: `file-discovery`, `resource-reading`, `yaml-loading`, `document-merge`,
  `schema-validation`, `variable-resolution`, `graph-building`, `snapshot-freezing`.
- Measurements: `files-loaded`, `duration`, `memory`, `graph-nodes`, `validation-errors`,
  `validation-warnings`, `warnings`.
- Occurrences: `configuration.loaded`, `configuration.merged`, `configuration.validated`,
  `configuration.resolved`, `configuration.snapshot_created` (plus `drift`, `reloaded`,
  `changed` where ConfigureIt emits them).
- A terminal **configuration snapshot** summarizing the resolution outcome.

## Architecture

```text
ConfigureIt (resolution)
        │  emits coarse stage occurrences + observations
        │  through ResolutionReporterInterface
        ▼
RunItResolutionReporter        (it-pack/configure-it-runit)
        │  forwards every fact to the ambient execution's recorders
        ▼
RunIt execution "configuration.resolution"
        │  (activities, measurements, observations, occurrences, snapshot)
        ▼
ArtifactPublisher (e.g. InMemoryArtifactPublisher / OTel / CloudEvents)
```

`ConfigureItExecutions::run($runtime, $name, $resolution)` wraps a resolution in a RunIt
execution of kind `configuration.resolution` and hands the operation a
`RunItResolutionReporter` bound to the execution scope. The reporter implements
ConfigureIt's own `ResolutionReporterInterface`, so any ConfigureIt resolver can be
observed with zero changes to ConfigureIt itself.

## Mapping

| ConfigureIt fact | RunIt artifact |
| --- | --- |
| `ConfigurationLoaded` | occurrence `configureit.configuration.loaded.v1` + `files-loaded` counter; closes `file-discovery` / `resource-reading` / `yaml-loading` activities |
| `ConfigurationMerged` | occurrence `configureit.configuration.merged.v1`; closes `document-merge` |
| `ConfigurationValidated` | occurrence `configureit.configuration.validated.v1` + `validation-errors` / `validation-warnings` counters; closes `schema-validation` |
| `ConfigurationResolved` | occurrence `configureit.configuration.resolved.v1` + `graph-nodes` counter; closes `variable-resolution` / `graph-building` |
| `ConfigurationDrift` / `Reloaded` / `Changed` | corresponding `configureit.configuration.*.v1` occurrence |
| `SnapshotCreated` | occurrence `configureit.configuration.snapshot_created.v1` + `duration` (ms) and `memory` (bytes) measurements + terminal `configureit.configuration.resolved` snapshot; closes `snapshot-freezing` |
| `Observation` (warning/error/info) | RunIt observation with the mapped severity; increments the `warnings` counter |
| (all activities) | opened as `configureit.activity.<name>.started.v1` when resolution begins |

The finer RunIt activity vocabulary is *expanded* from ConfigureIt's coarse stage
occurrences. ConfigureIt emits one `ConfigurationLoaded` per resolved document, so
`file-discovery`, `resource-reading`, and `yaml-loading` are closed together when that
stage completes. Stages without a corresponding ConfigureIt event (e.g. a resolution
with no processors never emits `ConfigurationMerged`) leave their activity opened, which
faithfully reflects "that stage did not run".

## Minimal usage

```php
<?php

declare(strict_types=1);

use ItPack\ConfigureIt\Adapter\FileReader;
use ItPack\ConfigureIt\Adapter\FileSource;
use ItPack\ConfigureIt\Adapter\PhpLoader;
use ItPack\ConfigureIt\Contract\Execution\ResolutionReporterInterface;
use ItPack\ConfigureIt\Wiring\Configurations;
use ItPack\ConfigureItRunit\ConfigureItExecutions;
use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

$runtime = RunItBuilder::create()
    ->withPublisher(new InMemoryArtifactPublisher())
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->build();

$snapshot = ConfigureItExecutions::run(
    $runtime,
    'config.resolve',
    static function (ResolutionReporterInterface $reporter) use ($dir) {
        $resolver = Configurations::builder()
            ->addReader(new FileReader())
            ->addSource(new FileSource($dir))
            ->addLoader(new PhpLoader())
            ->build($reporter);

        return $resolver->resolve();
    },
);
```

## Verification

`it-pack/configure-it-runit` ships an end-to-end suite
(`tests/ConfigureItExecutionsTest.php`) that:

- Runs a real ConfigureIt resolution inside a real RunIt execution and asserts the full
  artifact set (occurrences, activity boundaries, measurements, terminal snapshot).
- Drives the reporter directly to pin the validated / merge / warning / snapshot branches.
- Asserts a clean resolution emits no spurious observations.

Gate chain (all green): `php-cs-fixer` (0 files), `phpcs` (0), `phpstan` (0 errors),
`phpunit` (4 tests, 37 assertions, OK).

## Notes and follow-ups

- **ConfigureIt was not modified.** Its domain stays free of adapter dependencies; the
  bridge is entirely contained in `it-pack/configure-it-runit`. This satisfies the
  M14.7 requirement "Verify ConfigureIt domain remains free of adapter dependencies."
- **Activity representation.** The bridge models the eight documented activities as RunIt
  *occurrences* (`activity.<name>.started/finished.v1`) because ConfigureIt's reporter
  contract currently exposes only coarse stage occurrences, not operation-scoped
  activity start/stop. The precedent is `it-pack/derive-it-runit`, which maps
  `activityStarted` / `activityFinished` to occurrences for the same reason. If ConfigureIt
  later emits fine-grained activity boundaries through its reporter contract, the bridge can
  switch to RunIt Activity spans via `ExecutionScopeInterface::activities()->run(...)`.
- **Schema validation.** `schema-validation` / `validation-*` measurements exercise when a
  schema is supplied; the suite drives that branch directly through the reporter.
