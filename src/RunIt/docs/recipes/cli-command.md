# Recipe: CLI command

Wrap a CLI command in one RunIt execution and carry the invocation context (for
example the argument count) as classified context entries. The command's exit code
or output is preserved; on a thrown exception the failure is recorded and rethrown.

```php
<?php

declare(strict_types=1);

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Domain\Context\ContextEntry;
use ItPack\RunIt\Domain\Context\ContextKey;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

$runtime = RunItBuilder::create()
    ->withPublisher(new InMemoryArtifactPublisher())
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->build();

$argv = $_SERVER['argv'] ?? [];
$context = \ItPack\RunIt\Domain\Context\ExecutionContext::empty()->with(
    ContextEntry::public(ContextKey::fromString('argv.count'), (string) count($argv)),
);

$exitCode = $runtime->run(
    ExecutionDefinition::create(ExecutionName::fromString('cli.run'), ExecutionKind::fromString('cli'))
        ->withContext($context),
    static function (\ItPack\RunIt\Contract\Application\ExecutionScopeInterface $scope) use ($argv): int {
        // existing command logic
        $scope->observations()->record('command started', \ItPack\RunIt\Domain\Observation\ObservationSeverity::Info);
        return 0;
    },
);

exit($exitCode);
```

`withContext()` is only valid on the immutable `ExecutionDefinition`; the context is
attached to the execution and is available through `$scope->context()`. Keep
context values bounded and classified — secrets must use `ContextEntry::secret()`
and are never exported or serialized.

See [`../concepts/context.md`](../concepts/context.md) and
[`../architecture/context-propagation.md`](../architecture/context-propagation.md).
