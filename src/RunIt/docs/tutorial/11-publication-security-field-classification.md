# Tutorial 11 — Publication security & field classification

**Goal:** understand how RunIt classifies artifact fields before the sampler and
sink, how provenance verification works, and how security limits bound data.

## Classification before sampling

`ClassifyingArtifactPublisher` classifies artifact fields before the sampler
decides what to export. Unclassified fields are dropped with a payload-free health
notice; allowed fields pass through:

```php
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
    ->build()
;
```

A measurement recorded without classification is dropped before the sampler sees
it — delivery loss, not value redaction.

> **Run this:** `examples/field-classification.php` and `examples/measurement-drop.php`

## Provenance verification: export IDs vs business IDs

Every artifact is bound to an SDK-issued, verified export ID before publication.
Business-facing IDs (execution name/kind) are untrusted claims — they cannot forge
provenance:

```php
$execution = $scope->execution();
echo $execution->id()->toString(); // business ID — untrusted claim
// Only the SDK-issued export ID (verified by ProvenanceAuthority) establishes trust
```

Duplicate execution identity triggers an unverified report (payload-free).

> **Run this:** `examples/provenance-verification.php`

## Security limits

`SecurityLimits` bounds attribute count, key/value bytes, depth, occurrence
payload, snapshot payload, serialization depth/bytes, and tracked executions.
Oversized artifacts are dropped or replaced with a payload-free marker before they
can leave the process:

```php
use ItPack\RunIt\Domain\Shared\SecurityLimits;

$limits = SecurityLimits::default()
    ->withMaxAttributeCount(4)
    ->withMaxAttributeValueBytes(16);

$runtime = RunItBuilder::create()
    ->withPublisher(new InMemoryArtifactPublisher())
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->withSecurityLimits($limits)
    ->build()
;
```

Limits are fail-closed: oversized data never reaches external sinks.

> **Run this:** `examples/security-limits.php`

## What you learned

- Field classification runs before the sampler and sink — unclassified fields are
  dropped with a payload-free health notice.
- Only verified export IDs establish trust; business IDs alone are untrusted claims.
- `SecurityLimits` bounds artifact data; limits are fail-closed.

## Next

[Tutorial 12 — Custom factory boundary & provenance](12-custom-factory-boundary-and-provenance.md):
how custom publisher factories receive classified projections, never raw artifacts.
