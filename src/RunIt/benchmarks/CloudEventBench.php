<?php

declare(strict_types=1);

namespace Benchmarks;

use ItPack\RunIt\Adapter\CloudEvents\CloudEvent;
use ItPack\RunIt\Adapter\CloudEvents\Mapping\DefaultOccurrenceToCloudEventMapper;
use ItPack\RunIt\Adapter\CloudEvents\Serialization\DefaultCloudEventJsonSerializer;
use ItPack\RunIt\Domain\Activity\ActivityId;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Occurrence\Occurrence;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;
use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\OutputTimeUnit;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Subject;
use PhpBench\Attributes\Warmup;
use RuntimeException;

/** Measures occurrence mapping and structured JSON serialization separately. */
#[OutputTimeUnit('microseconds')]
#[Warmup(2)]
#[Iterations(5)]
#[Revs(1000)]
#[BeforeMethods('setUp')]
#[AfterMethods('verify')]
final class CloudEventBench
{
    private Occurrence $occurrence;
    private DefaultOccurrenceToCloudEventMapper $mapper;
    private DefaultCloudEventJsonSerializer $serializer;
    private CloudEvent $event;
    private string $json;

    public function setUp(): void
    {
        $rows = [];
        for ($i = 0; $i < 16; ++$i) {
            $rows[] = ['sku' => 'item-' . $i, 'quantity' => $i + 1, 'details' => ['region' => 'eu']];
        }

        $this->occurrence = Occurrence::create(
            OccurrenceId::fromString('occ-bench'),
            ExecutionId::fromString('exec-bench'),
            OccurrenceType::fromString('checkout.completed.v1'),
            'it-pack/benchmark',
            Instant::of(1600),
            ['items' => $rows, 'success' => true],
            ActivityId::fromString('act-bench'),
            subject: 'checkout',
            extensionAttributes: AttributeCollection::fromArray(['correlation.id' => 'corr-bench', 'tenant' => 'test']),
        );
        $this->mapper = new DefaultOccurrenceToCloudEventMapper();
        $this->serializer = new DefaultCloudEventJsonSerializer();
        $this->event = $this->mapper->map($this->occurrence);
        $this->json = $this->serializer->serialize($this->event);
    }

    #[Subject]
    public function benchMap(): void
    {
        $this->event = $this->mapper->map($this->occurrence);
    }

    #[Subject]
    public function benchSerialize(): void
    {
        $this->json = $this->serializer->serialize($this->event);
    }

    public function verify(): void
    {
        $event = $this->event;
        if ($event->id !== 'occ-bench' || $event->source !== 'it-pack/benchmark'
            || $event->type !== 'checkout.completed.v1' || $event->subject !== 'checkout'
            || $event->time !== '1970-01-01T00:26:40Z' || $event->specVersion !== '1.0'
            || $event->data !== $this->occurrence->data()
            || $event->extensions !== ['executionid' => 'exec-bench', 'activityid' => 'act-bench',
                'sequence' => 0, 'correlationid' => 'corr-bench', 'tenant' => 'test']
            || $this->serializer->deserialize($this->json)->toArray() !== $event->toArray()
        ) {
            throw new RuntimeException('CloudEvent mapping or JSON round-trip mismatch.');
        }
    }
}
