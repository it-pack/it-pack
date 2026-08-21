# Recipe: queue job

Wrap a queue-job handler in one RunIt execution. Carry the job id as classified
context, record a processing activity, and raise an occurrence when the job is
handled. The handler's return value (or exception) passes through unchanged.

```php
<?php

declare(strict_types=1);

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Domain\Activity\ActivityDefinition;
use ItPack\RunIt\Domain\Activity\ActivityKind;
use ItPack\RunIt\Domain\Activity\ActivityName;
use ItPack\RunIt\Domain\Context\ContextEntry;
use ItPack\RunIt\Domain\Context\ContextKey;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

$runtime = RunItBuilder::create()
    ->withPublisher(new InMemoryArtifactPublisher())
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->build();

$payload = /* the job message, e.g. JSON-decoded */;
$jobId = (string) ($payload['id'] ?? 'unknown');
$context = \ItPack\RunIt\Domain\Context\ExecutionContext::empty()->with(
    ContextEntry::public(ContextKey::fromString('job.id'), $jobId),
);

$runtime->run(
    ExecutionDefinition::create(ExecutionName::fromString('queue.job'), ExecutionKind::fromString('job'))
        ->withContext($context),
    static function (\ItPack\RunIt\Contract\Application\ExecutionScopeInterface $scope) use ($payload): void {
        $scope->activities()->run(
            ActivityDefinition::create(ActivityName::fromString('process'), ActivityKind::fromString('handler')),
            static function () use ($payload): void {
                // process the job payload
            },
        );

        $scope->occurrences()->record(
            OccurrenceType::fromString('queue.job.processed.v1'),
            'worker',
            data: ['job_id' => $jobId],
        );
    },
);
```

Each job is a separate execution with its own identity and artifacts, so jobs never
share context. See [`../concepts/occurrence.md`](../concepts/occurrence.md).
