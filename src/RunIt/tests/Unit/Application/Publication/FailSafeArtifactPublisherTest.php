<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\FailOpenPolicy;
use ItPack\RunIt\Application\Publication\FailSafeArtifactPublisher;
use ItPack\RunIt\Application\Publication\RedactingArtifactPublisher;
use ItPack\RunIt\Application\Publication\SamplingArtifactPublisher;
use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Tests\Support\Fixture\EmptyArtifact;

#[CoversClass(FailSafeArtifactPublisher::class)]
final class FailSafeArtifactPublisherTest extends Unit
{
    public function testRedactorFailureIsIsolatedByFailSafePublisher(): void
    {
        $redactor = new class () implements RedactorInterface {
            public function redact(ExecutionArtifactInterface $artifact): ExecutionArtifactInterface
            {
                throw new RuntimeException('redactor is broken');
            }
        };

        $inner = new InMemoryArtifactPublisher();
        $safe = new FailSafeArtifactPublisher(
            new RedactingArtifactPublisher($redactor, $inner),
            new FailOpenPolicy(),
        );

        $safe->publish(new EmptyArtifact());

        self::assertCount(
            0,
            $inner->published(),
            'redactor failure must be swallowed by the fail-safe boundary'
        );
    }

    public function testSamplingPolicyFailureIsIsolatedByFailSafePublisher(): void
    {
        $policy = new class () implements SamplingPolicyInterface {
            public function sample(ExecutionArtifactInterface $artifact): bool
            {
                throw new RuntimeException('sampling policy is broken');
            }
        };

        $inner = new InMemoryArtifactPublisher();
        $safe = new FailSafeArtifactPublisher(
            new SamplingArtifactPublisher($policy, $inner),
            new FailOpenPolicy(),
        );

        $safe->publish(new EmptyArtifact());

        self::assertCount(
            0,
            $inner->published(),
            'sampling policy failure must be swallowed by the fail-safe boundary'
        );
    }
}
