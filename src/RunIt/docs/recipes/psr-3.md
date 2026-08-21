# Recipe: PSR-3

Send RunIt observations to any PSR-3 logger by using `ObservationLoggerPublisher` as
the runtime's publisher. Observations are mapped to log records (severity → PSR-3
level, stable message template → message, attributes → context). Redaction runs
before logging when a `RedactorInterface` is supplied. Requires `psr/log`. See
[`../integrations/psr.md`](../integrations/psr.md).

```php
<?php

declare(strict_types=1);

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Psr\Log\ObservationLoggerPublisher;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\Builder\RunItBuilder;
use Psr\Log\LoggerInterface;

/** @var LoggerInterface $psr3Logger */
$publisher = new ObservationLoggerPublisher($psr3Logger);

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
    },
);

// 'payment accepted' is now written to $psr3Logger at the Info level
```

For the reverse direction (a PSR-3 logger that *records* RunIt observations), use
`ObservationRecordingLogger`, which is guarded against log → observation → log
recursion. See [`../integrations/psr.md`](../integrations/psr.md) and
[`../integrations/adapter-failure-policies.md`](../integrations/adapter-failure-policies.md).
