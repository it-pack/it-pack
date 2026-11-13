<?php

declare(strict_types=1);

namespace Benchmarks;

use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Application\Publication\ArtifactRedactionConfig;
use ItPack\RunIt\Application\Publication\DefaultArtifactRedactor;
use ItPack\RunIt\Application\Publication\RedactingArtifactPublisher;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Shared\DeterministicHashCalculator;
use ItPack\RunIt\Domain\Shared\Instant;
use ItPack\RunIt\Domain\Snapshot\Snapshot;
use ItPack\RunIt\Domain\Snapshot\SnapshotId;
use ItPack\RunIt\Domain\Snapshot\SnapshotType;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\OutputTimeUnit;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Subject;
use PhpBench\Attributes\Warmup;
use RuntimeException;

/** Nested payload projection, recursive key masking, JSON normalization and publication. */
#[OutputTimeUnit('microseconds')]
#[Warmup(2)]
#[Iterations(5)]
#[Revs(100)]
final class NestedSnapshotBench
{
    private Snapshot $snapshot;
    private DefaultArtifactRedactor $redactor;
    private JsonSnapshotSerializer $serializer;
    private RedactingArtifactPublisher $publisher;

    #[Subject, BeforeMethods('setUpSmall')]
    public function benchProjection8(): void
    {
        $this->snapshot->normalize();
    }

    #[Subject, BeforeMethods('setUpLarge')]
    public function benchProjection64(): void
    {
        $this->snapshot->normalize();
    }

    #[Subject, BeforeMethods('setUpSmall')]
    public function benchRedaction8(): void
    {
        $this->redactor->redact($this->snapshot);
    }

    #[Subject, BeforeMethods('setUpLarge')]
    public function benchRedaction64(): void
    {
        $this->redactor->redact($this->snapshot);
    }

    #[Subject, BeforeMethods('setUpSmall')]
    public function benchSerialization8(): void
    {
        $this->serializer->normalize($this->snapshot->payload());
    }

    #[Subject, BeforeMethods('setUpLarge')]
    public function benchSerialization64(): void
    {
        $this->serializer->normalize($this->snapshot->payload());
    }

    #[Subject, BeforeMethods('setUpSmall')]
    public function benchPublisher8(): void
    {
        $this->publisher->publish($this->snapshot);
    }

    #[Subject, BeforeMethods('setUpLarge')]
    public function benchPublisher64(): void
    {
        $this->publisher->publish($this->snapshot);
    }

    public function setUpSmall(): void
    {
        $this->setUp(8);
    }

    public function setUpLarge(): void
    {
        $this->setUp(64);
    }

    private function setUp(int $rows): void
    {
        $items = [];
        for ($i = 0; $i < $rows; ++$i) {
            $items[] = [
                'sku' => 'product-' . $i,
                'quantity' => 2,
                'customer' => ['email' => 'customer@example.test', 'address' => ['token' => 'private-token', 'city' => 'Berlin']],
            ];
        }
        $payload = ['order' => ['currency' => 'EUR', 'items' => $items]];
        $this->snapshot = Snapshot::create(
            SnapshotId::fromString('snap-bench'),
            ExecutionId::fromString('exec-bench'),
            SnapshotType::fromString('execution.snapshot'),
            1,
            $payload,
            new DeterministicHashCalculator(),
            createdAt: Instant::of(1000),
        );
        $this->redactor = new DefaultArtifactRedactor(new ArtifactRedactionConfig(sensitiveDataKeys: ['email', 'token']));
        $this->serializer = new JsonSnapshotSerializer();
        $sink = new class () implements ArtifactPublisherInterface {
            public string $json = '';

            public function publish(ExecutionArtifactInterface $artifact): void
            {
                $this->json = json_encode($artifact->normalize(), JSON_THROW_ON_ERROR);
            }
        };
        $this->publisher = new RedactingArtifactPublisher($this->redactor, $sink);

        $expectedPayload = $payload;
        foreach ($expectedPayload['order']['items'] as &$item) {
            $item['customer']['email'] = '[redacted]';
            $item['customer']['address']['token'] = '[redacted]';
        }
        unset($item);
        $expected = $this->snapshot->normalize();
        $expected['payload'] = $expectedPayload;
        unset($expected['content_hash']);
        $this->publisher->publish($this->snapshot);
        if ($this->snapshot->payload() !== $payload
            || $this->snapshot->normalize()['payload'] !== $payload
            || $this->serializer->normalize($payload) !== $payload
            || $this->redactor->redact($this->snapshot)->normalize() !== $expected
            || json_decode($sink->json, true, flags: JSON_THROW_ON_ERROR) !== $expected
        ) {
            throw new RuntimeException('Nested snapshot benchmark correctness check failed.');
        }
    }
}
