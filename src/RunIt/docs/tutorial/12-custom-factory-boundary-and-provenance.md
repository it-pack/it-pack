# Tutorial 12 — Custom factory boundary & provenance

**Goal:** understand how custom publisher stack factories receive classified
projections — never raw artifacts or business IDs — and how redaction works.

## The custom factory boundary

The SDK applies its redactor and field classification **before** the custom
publisher stack. Custom `PublisherStackFactoryInterface` implementations receive
`null` for the redactor argument (the SDK handles redaction) and only classified
projections — never raw artifacts:

```php
use ItPack\RunIt\Contract\Port\PublisherStackFactoryInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;

final class ClassifyingStackFactory implements PublisherStackFactoryInterface
{
    public function compose(
        ArtifactPublisherInterface $base,
        ?RedactorInterface $redactor,
        ?SamplingPolicyInterface $samplingPolicy,
    ): ArtifactPublisherInterface {
        // $redactor is null — SDK handled redaction
        // Artifacts reaching here are classified projections
        return $base;
    }
}

$runtime = RunItBuilder::create()
    ->withPublisher(new InMemoryArtifactPublisher())
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->withPublisherStackFactory(new ClassifyingStackFactory())
    ->build()
;
```

> **Run this:** `examples/custom-factory-boundary.php`

## Custom redaction

`DefaultArtifactRedactor` masks configured sensitive keys before publication.
Observation messages are redacted by default:

```php
use ItPack\RunIt\Application\Publication\ArtifactRedactionConfig;
use ItPack\RunIt\Application\Publication\DefaultArtifactRedactor;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

$redactor = new DefaultArtifactRedactor(
    new ArtifactRedactionConfig(
        sensitiveAttributeKeys: ['credit_card', 'email'],
        sensitiveDataKeys: ['password', 'token'],
        marker: '[redacted]',
        redactThrowableMessages: true,
        redactObservationMessages: true,
    ),
);

$runtime = RunItBuilder::create()
    ->withPublisher(new InMemoryArtifactPublisher())
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->withRedactor($redactor)
    ->build()
;
```

Explicit `withRedactor(null)` disables the redactor but **retains** classification —
the SDK boundary stays in place.

> **Run this:** `examples/redaction-custom.php`

## Health reporter fallback

When the health reporter throws, RunIt falls back to fixed, payload-free
diagnostics via `error_log`. No exception messages or artifact identifiers leak:

```php
$runtime = RunItBuilder::create()
    ->withPublisher(new InMemoryArtifactPublisher())
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->withPublicationFailureHandler(static function (\Throwable $failure): void {
        // Fallback reporting — payload-free, no identifiers
    })
    ->build()
;
```

> **Run this:** `examples/health-reporter-fallback.php`

## What you learned

- Custom factories receive `null` redactor and classified projections — never raw
  artifacts or business IDs.
- `DefaultArtifactRedactor` masks configured sensitive keys; observation messages
  are redacted by default.
- Health reporter failures fall back to payload-free diagnostics.

## Next

[Tutorial 13 — Publisher failure policies & resilience](13-publisher-failure-policies-and-resilience.md):
how publication failures are handled without replacing your business exception.
