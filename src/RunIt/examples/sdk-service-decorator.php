<?php

declare(strict_types=1);

/**
 * RunIt SDK — instrumenting a service through composition (no proxy generation).
 *
 * RunIt 1.0.0 deliberately avoids runtime proxy generation. Instead, wrap the service behind a
 * `final readonly` decorator that implements the same interface and forwards each call through the
 * runtime. The wrapped contract is preserved exactly, dependency inversion is demonstrated (the
 * decorator depends only on the service interface and the RunIt runtime), and the original result
 * and thrown exception are unchanged.
 *
 * Run from the package root: `php examples/sdk-service-decorator.php`
 */

require __DIR__ . '/../vendor/autoload.php';

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

interface InvoiceService
{
    public function issue(string $orderId): string;
}

final readonly class DefaultInvoiceService implements InvoiceService
{
    public function issue(string $orderId): string
    {
        return 'INV-' . $orderId;
    }
}

/**
 * Instrumented, dependency-inverted decorator. It implements InvoiceService, so any consumer that
 * depends on the interface keeps working without knowing RunIt is present.
 */
final readonly class InstrumentedInvoiceService implements InvoiceService
{
    public function __construct(
        private InvoiceService $inner,
        private \ItPack\RunIt\SDK\RunItInterface $runIt,
    ) {
    }

    public function issue(string $orderId): string
    {
        return $this->runIt->run(
            ExecutionDefinition::create(ExecutionName::fromString('invoice.issue'), ExecutionKind::fromString('operation')),
            fn (): string => $this->inner->issue($orderId),
        );
    }
}

$runtime = RunItBuilder::create()
    ->withPublisher(new InMemoryArtifactPublisher())
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->build();

$service = new InstrumentedInvoiceService(new DefaultInvoiceService(), $runtime);

echo $service->issue('42') . PHP_EOL;
echo $service->issue('43') . PHP_EOL;
