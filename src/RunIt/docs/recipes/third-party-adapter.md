# Recipe: third-party adapter

Build a custom adapter by implementing the `ArtifactPublisherInterface` port. A
publisher depends only on `ExecutionArtifactInterface` and the port — never on the
domain concrete classes or a vendor SDK — so it slots into any RunIt runtime through
`withPublisher()`.

```php
<?php

declare(strict_types=1);

use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;

final class StderrPublisher implements ArtifactPublisherInterface
{
    public function publish(ExecutionArtifactInterface $artifact): void
    {
        fwrite(STDERR, $artifact->normalize()['id'] . PHP_EOL);
    }
}
```

```php
<?php

declare(strict_types=1);

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

$runtime = RunItBuilder::create()
    ->withPublisher(new StderrPublisher())
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->build();

$runtime->run(
    ExecutionDefinition::create(ExecutionName::fromString('checkout.process'), ExecutionKind::fromString('operation')),
    static function (): void {
        // work
    },
);
```

To claim RunIt compatibility, verify the adapter against the conformance verifiers
rather than extending a test base class. The verifiers assert identity, ownership,
timing, normalization, and redaction behavior for first-party and third-party
adapters alike:

```bash
vendor/bin/codecept run Conformance
```

A complete, runnable third-party publisher is in
`examples/conformance/third-party-adapter.php`; the verification contract is in
[`../conformance.md`](../conformance.md). Keep adapters framework-neutral: do not
configure global providers, do not select an exporter, and let the surrounding
publication pipeline own failure handling. See
[`../architecture/hexagonal-architecture.md`](../architecture/hexagonal-architecture.md)
and [`../integrations/adapter-failure-policies.md`](../integrations/adapter-failure-policies.md).
