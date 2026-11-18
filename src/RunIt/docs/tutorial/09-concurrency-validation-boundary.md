# Tutorial 9 — Concurrency validation boundary

**Goal:** understand how RunIt rejects unsafe storage/engine pairings, and how to
add your own validation seam.

## The built-in guard

`RunItBuilder::build()` inspects the engine's `EngineCapability` and the chosen
`ContextStorageInterface`. If the engine declares `concurrent: true` and the
storage is `StackContextStorage` (shared across the call stack), the build throws
`IncompatibleContextStorageException`. This prevents context from leaking across
concurrent executions.

```php
use ItPack\RunIt\Contract\Application\EngineCapability;
use ItPack\RunIt\Contract\Application\ExecutionEngineInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Domain\Exception\IncompatibleContextStorageException;

// An engine that declares concurrent execution
$concurrentEngine = new class implements ExecutionEngineInterface {
    public function run(ExecutionScopeInterface $scope, callable $operation): mixed
    {
        return $operation($scope);
    }

    public function capability(): EngineCapabilityInterface
    {
        return new EngineCapability(supportsFibers: false, supportsCoroutines: false, concurrent: true);
    }
};

try {
    RunItBuilder::create()
        ->withPublisher(new InMemoryArtifactPublisher())
        ->withClock(new SystemMonotonicClock())
        ->withSequenceAllocator(new InMemorySequenceAllocator())
        ->withSnapshotSerializer(new JsonSnapshotSerializer())
        ->withIdentifierGenerator(new RandomIdentifierGenerator())
        ->withContextStorage(new StackContextStorage())
        ->withExecutionEngine($concurrentEngine)
        ->build();
} catch (IncompatibleContextStorageException $e) {
    echo "rejected: " . $e->getMessage();
}
```

> **Run this:** `examples/concurrency-validation.php`

## Custom validator seam

The built-in guard covers storage/engine compatibility. For application-specific
rules (for example, "never pair storage X with engine Y"), use
`withConcurrencyValidator()`. The closure receives the engine capability and the
storage instance, and can throw `IncompatibleContextStorageException` for custom
violations:

```php
$customValidator = static function (
    ?EngineCapability $capability,
    ContextStorageInterface $storage,
): void {
    if ($capability?->concurrent && $storage instanceof StackContextStorage) {
        throw IncompatibleContextStorageException::sharedStackWithConcurrentEngine();
    }
};

$runtime = RunItBuilder::create()
    ->withPublisher(new InMemoryArtifactPublisher())
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->withContextStorage(new StackContextStorage())
    ->withExecutionEngine(new FiberExecutionEngine())
    ->withConcurrencyValidator($customValidator)
    ->build()
;
```

> **Run this:** `examples/concurrency-validation.php` (third scenario)

## What you learned

- Build-time validation rejects `StackContextStorage` + concurrent engine.
- `withConcurrencyValidator()` adds application-specific validation without changing
  the storage contract.
- The closure receives `EngineCapabilityInterface` + `ContextStorageInterface`.

## Next

[Tutorial 10 — Activity provenance & overlap](10-activity-provenance-and-overlap.md): how
overlapping activities retain independent attribution.
