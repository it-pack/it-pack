<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Factory\PublisherStackFactory;
use ItPack\RunIt\Application\Publication\CallbackHealthReporter;
use ItPack\RunIt\Application\Publication\DefaultArtifactRedactor;
use ItPack\RunIt\Application\Publication\DefaultOccurrenceClassifier;
use ItPack\RunIt\Application\Publication\DefaultSdkRedactor;
use ItPack\RunIt\Application\Publication\ProvenanceAuthority;
use ItPack\RunIt\Application\Publication\VerifiedArtifactProjection;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Occurrence\Occurrence;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use RuntimeException;
use Throwable;

#[CoversClass(DefaultOccurrenceClassifier::class)]
#[UsesClass(InMemoryArtifactPublisher::class)]
#[UsesClass(CallbackHealthReporter::class)]
#[UsesClass(PublisherStackFactory::class)]
#[UsesClass(ProvenanceAuthority::class)]
#[UsesClass(DefaultSdkRedactor::class)]
#[UsesClass(VerifiedArtifactProjection::class)]
final class DefaultOccurrenceClassifierTest extends Unit
{
    public function testDefaultStackSanitizesBeforeSamplerAndSink(): void
    {
        $authority = new ProvenanceAuthority();
        $executionId = $authority->issueExportId('execution', 'execution', 'business-exec', 'business-exec');
        $occurrenceId = $authority->bindArtifact(
            'occurrence',
            'occurrence',
            'business-exec',
            'business-event',
            'execution-occurrence',
        );
        $occurrence = Occurrence::create(
            OccurrenceId::fromString('business-event'),
            ExecutionId::fromString('business-exec'),
            OccurrenceType::fromString('secret.event.v2'),
            'secret-source',
            Instant::of(123),
            ['nested-secret-key' => ['deep' => 'secret-value']],
            subject: 'secret-subject',
            dataSchema: 'secret-schema',
            contentType: 'secret-content',
            extensionAttributes: AttributeCollection::fromArray(['secret-extension' => 'secret-value']),
        );
        self::assertTrue($authority->attest($occurrence, $occurrenceId));
        $policy = new class () implements SamplingPolicyInterface {
            public ?ExecutionArtifactInterface $seen = null;

            public function sample(ExecutionArtifactInterface $artifact): bool
            {
                $this->seen = $artifact;
                return true;
            }
        };
        $sink = new InMemoryArtifactPublisher();
        $stack = new PublisherStackFactory()->compose($sink, new DefaultSdkRedactor(), $policy, authority: $authority);
        $stack->publish($occurrence);

        self::assertInstanceOf(OccurrenceInterface::class, $policy->seen);
        self::assertCount(1, $sink->published());
        self::assertInstanceOf(OccurrenceInterface::class, $sink->published()[0]);
        foreach ([$policy->seen, $sink->published()[0]] as $projected) {
            $fields = $projected->normalize();
            self::assertSame($occurrenceId, $fields['id']);
            self::assertSame($executionId, $fields['execution_id']);
            self::assertSame('runit.redacted.v1', $fields['type']);
            self::assertStringNotContainsString('secret', json_encode($fields, JSON_THROW_ON_ERROR));
            self::assertStringNotContainsString('business', json_encode($fields, JSON_THROW_ON_ERROR));
        }

        self::assertSame('secret-source', $occurrence->source());
    }

    public function testInvalidRequiredFieldsAreReportedWithoutPayload(): void
    {
        $reports = [];
        $health = new CallbackHealthReporter(static function (
            Throwable $error,
            ExecutionArtifactInterface $artifact,
        ) use (&$reports): void {
            $reports[] = [$error->getMessage(), $artifact->normalize()];
        });
        $sink = new InMemoryArtifactPublisher();
        $classifier = new DefaultOccurrenceClassifier($sink, $health, new SecurityLimits());
        $classifier->publish(new VerifiedArtifactProjection([
            'id' => 'id', 'execution_id' => 'exec', 'time' => 'secret', 'sequence' => 1,
        ], 'occurrence'));
        self::assertCount(0, $sink->published());
        self::assertSame([['Default occurrence classification failed.', [
            'id' => 'redacted', 'type' => 'runit.artifact.redacted',
        ]]], $reports);
    }

    public function testNegativeTimeOrSequenceIsDroppedWithoutPayload(): void
    {
        $reports = [];
        $health = new CallbackHealthReporter(static function (
            Throwable $error,
            ExecutionArtifactInterface $artifact,
        ) use (&$reports): void {
            $reports[] = [$error->getMessage(), $artifact->normalize()];
        });
        $sink = new InMemoryArtifactPublisher();
        $classifier = new DefaultOccurrenceClassifier($sink, $health, new SecurityLimits());
        foreach (['time', 'sequence'] as $field) {
            $fields = [
                'id' => 'secret-id', 'execution_id' => 'secret-execution', 'time' => 0,
                'sequence' => 0, 'data' => 'secret-data',
            ];
            $fields[$field] = -1;
            $classifier->publish(new VerifiedArtifactProjection($fields, 'occurrence'));
        }

        self::assertCount(0, $sink->published());
        self::assertCount(2, $reports);
        self::assertStringNotContainsString('secret', json_encode($reports, JSON_THROW_ON_ERROR));
    }

    public function testExplicitRedactorChoicesStillApplyOccurrencePolicy(): void
    {
        foreach ([null, new DefaultArtifactRedactor()] as $redactor) {
            $authority = new ProvenanceAuthority();
            $executionId = $authority->issueExportId('execution', 'execution', 'exec', 'exec');
            $id = $authority->bindArtifact('occurrence', 'occurrence', 'exec', 'event', 'execution-occurrence');
            $occurrence = Occurrence::create(
                OccurrenceId::fromString('event'),
                ExecutionId::fromString('exec'),
                OccurrenceType::fromString('custom.event.v1'),
                'custom-source',
                Instant::of(123),
                ['nested' => ['value' => 'custom-value']],
            );
            self::assertTrue($authority->attest($occurrence, $id));
            $policy = new class () implements SamplingPolicyInterface {
                public ?ExecutionArtifactInterface $seen = null;

                public function sample(ExecutionArtifactInterface $artifact): bool
                {
                    $this->seen = $artifact;
                    return true;
                }
            };
            $sink = new InMemoryArtifactPublisher();
            $stack = new PublisherStackFactory()->compose(
                $sink,
                $redactor,
                $policy,
                authority: $authority,
            );
            $stack->publish($occurrence);
            self::assertInstanceOf(OccurrenceInterface::class, $policy->seen);
            self::assertCount(1, $sink->published());
            foreach ([$policy->seen, $sink->published()[0]] as $artifact) {
                $fields = $artifact->normalize();
                self::assertSame($id, $fields['id']);
                self::assertSame($executionId, $fields['execution_id']);
                self::assertSame('runit.redacted.v1', $fields['type']);
                self::assertSame('runit:redacted', $fields['source']);
                self::assertNull($fields['data']);
                self::assertStringNotContainsString('custom', json_encode($fields, JSON_THROW_ON_ERROR));
            }
        }
    }

    public function testThrowingHealthReporterDoesNotLeakItsException(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'runit-health-');
        self::assertIsString($path);
        $previous = ini_set('error_log', $path);
        try {
            $health = new class () implements InternalHealthReporterInterface {
                public function report(Throwable $error, ExecutionArtifactInterface $artifact): void
                {
                    throw new RuntimeException('secret-health-error');
                }
            };
            $classifier = new DefaultOccurrenceClassifier(
                new InMemoryArtifactPublisher(),
                $health,
                new SecurityLimits(),
            );
            $classifier->publish(new VerifiedArtifactProjection([
                'id' => 'id', 'execution_id' => 'exec', 'time' => 'secret-time', 'sequence' => 1,
            ], 'occurrence'));
            $log = file_get_contents($path);
            self::assertIsString($log);
            self::assertStringContainsString('Default occurrence classification failed.', $log);
            self::assertStringNotContainsString('secret', $log);
        } finally {
            if ($previous !== false) {
                ini_set('error_log', $previous);
            }

            unlink($path);
        }
    }
}
