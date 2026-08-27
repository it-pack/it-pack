# derive-it-runit

> RunIt execution-model integration for **DeriveIt** — the only place where the
> two packages meet.

DeriveIt emits derivation telemetry (activities, measurements, observations,
occurrences) through its own dependency-free `DeriveEventSinkInterface`. RunIt
models executions. This package bridges the two: a derivation run becomes a
first-class **execution**, and every pipeline fact lands on that execution's
recorders — without DeriveIt importing a single RunIt symbol, and vice versa.

## Installation

```bash
composer require it-pack/derive-it-runit
```

(Pulls in `it-pack/derive-it` and `it-pack/run-it`.)

## Usage

```php
use ItPack\DeriveIt\DeriveIt;
use ItPack\DeriveIt\Generator\DeriveConfig;
use ItPack\DeriveItRunit\DeriveItExecutions;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

$runtime = /* your RunItBuilder-built runtime */;

$source = DeriveItExecutions::run(
    $runtime,
    'derive.flat',
    fn ($sink) => DeriveIt::deriveWithResult($class, new DeriveConfig(), $sink)->source,
);
```

Every facade method that performs a derivation accepts an optional trailing
`?DeriveEventSinkInterface $eventSink = null`; hand it the sink your closure
receives and the whole pipeline attaches to the ambient execution.

## Fact mapping

| DeriveIt fact | RunIt artifact |
|---|---|
| `activityStarted/Finished` | occurrence `deriveit.activity.{started,finished}.v1` |
| `measured` | measurement named `deriveit.<name>` (`count`→counter, `By`→size, `ms`→duration, `%`→ratio, else gauge) |
| `observed` | observation with mapped severity |
| `occurred` | occurrence `deriveit.<type>.v1` |

Execution vocabulary: kind `derivation.run`, names like `derive.flat`,
`derive.family`, `derive.diff`.

## Commands

```bash
composer test:unit   # PHPUnit incl. end-to-end execution test
composer analyse     # PHPStan level 8
composer cs:check    # PHP-CS-Fixer dry-run
```
