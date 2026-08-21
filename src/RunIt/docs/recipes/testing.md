# Recipe: testing

Test RunIt instrumented code without a real backend by using
`InMemoryArtifactPublisher`, which exposes `published()` so you can assert on emitted
artifacts. Because artifacts are immutable and `normalize()` is deterministic, you
can assert on exact projections.

```php
<?php

declare(strict_types=1);

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Occurrence\Occurrence;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

$publisher = new InMemoryArtifactPublisher();

$runtime = RunItBuilder::create()
    ->withPublisher($publisher)
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->build();

$runtime->run(
    ExecutionDefinition::create(ExecutionName::fromString('checkout.process'), ExecutionKind::fromString('operation')),
    static function (\ItPack\RunIt\Contract\Application\ExecutionScopeInterface $scope): void {
        $scope->observations()->record('payment accepted', \ItPack\RunIt\Domain\Observation\ObservationSeverity::Info);
        $scope->occurrences()->record(
            \ItPack\RunIt\Domain\Occurrence\OccurrenceType::fromString('checkout.done.v1'),
            'checkout',
        );
    },
);

$observations = array_filter($publisher->published(), static fn ($a) => $a instanceof Observation);
$occurrences  = array_filter($publisher->published(), static fn ($a) => $a instanceof Occurrence);

assert(count($observations) === 1);
assert(count($occurrences) === 1);
```

For adapter authors, verify a third-party port against the framework-neutral
conformance suite instead of a base class:

```bash
vendor/bin/codecept run Conformance
```

See [`../conformance.md`](../conformance.md) and
`examples/conformance/third-party-adapter.php`.
