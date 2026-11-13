<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\CallbackHealthReporter;
use ItPack\RunIt\Application\Publication\ClassifiedTraceObservation;
use ItPack\RunIt\Application\Publication\DefaultObservationClassifier;
use ItPack\RunIt\Application\Publication\ErrorOverrideSamplePolicy;
use ItPack\RunIt\Application\Publication\NeverSamplePolicy;
use ItPack\RunIt\Application\Publication\RedactedArtifact;
use ItPack\RunIt\Application\Publication\SamplingArtifactPublisher;
use ItPack\RunIt\Application\Publication\SeveritySamplePolicy;
use ItPack\RunIt\Application\Publication\VerifiedArtifactProjection;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Throwable;

#[CoversClass(DefaultObservationClassifier::class)]
final class DefaultObservationClassifierTest extends Unit
{
    public function testUnclassifiedFieldsAreRemovedBeforeTypedErrorSampling(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $sampling = new SamplingArtifactPublisher(new ErrorOverrideSamplePolicy(new NeverSamplePolicy()), $sink);
        $classifier = new DefaultObservationClassifier($sampling, null, new SecurityLimits());
        $fields = [
            'id' => 'export-observation', 'execution_id' => 'export-execution', 'activity_id' => null,
            'severity' => 'error', 'level' => 0, 'message' => 'secret message',
            'diagnostic_code' => 'secret-code', 'timestamp' => 123, 'sequence' => 1,
            'attributes' => ['secret-key' => ['nested' => 'secret-value']],
            'throwable' => ['class' => 'secret-class', 'message' => 'secret-throwable'],
            'extra-secret-key' => 'secret-extra',
        ];
        $original = new RedactedArtifact($fields, 'observation');
        $classifier->publish($original);

        self::assertCount(1, $sink->published());
        $observation = $sink->published()[0];
        self::assertInstanceOf(ObservationInterface::class, $observation);
        self::assertSame(400, $observation->severity()->level());
        self::assertSame('[redacted]', $observation->message());
        self::assertSame([], $observation->attributes());
        self::assertNull($observation->diagnosticCode());
        self::assertNull($observation->throwableDescription());
        self::assertStringNotContainsString('secret', json_encode($observation->normalize(), JSON_THROW_ON_ERROR));
        self::assertSame($fields, $original->normalize());
    }

    public function testInvalidSeverityIsDroppedWithPayloadFreeReport(): void
    {
        $reports = [];
        $health = new CallbackHealthReporter(static function (
            Throwable $error,
            ExecutionArtifactInterface $artifact,
        ) use (&$reports): void {
            $reports[] = [$error->getMessage(), $artifact->normalize()];
        });
        $sink = new InMemoryArtifactPublisher();
        $classifier = new DefaultObservationClassifier($sink, $health, new SecurityLimits());
        $classifier->publish(new VerifiedArtifactProjection([
            'id' => 'export-observation', 'execution_id' => 'export-execution',
            'severity' => 'secret-severity', 'timestamp' => 1, 'sequence' => 1,
            'message' => 'secret-message',
        ], 'observation'));

        self::assertCount(0, $sink->published());
        self::assertSame([['Default observation classification failed.', [
            'id' => 'redacted', 'type' => 'runit.artifact.redacted',
        ]]], $reports);
    }

    public function testThrowingHealthReporterDoesNotLeakItsMessageToFallback(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'runit-classifier-');
        $previous = (string) ini_get('error_log');
        try {
            ini_set('error_log', $path);
            $health = new class () implements InternalHealthReporterInterface {
                public function report(Throwable $error, ExecutionArtifactInterface $artifact): void
                {
                    throw new RuntimeException('secret-reporter-message');
                }
            };
            $sink = new InMemoryArtifactPublisher();
            $classifier = new DefaultObservationClassifier($sink, $health, new SecurityLimits());
            $classifier->publish(new VerifiedArtifactProjection([
                'id' => 'export-observation', 'execution_id' => 'export-execution',
                'severity' => 'invalid', 'timestamp' => 1, 'sequence' => 1,
            ], 'observation'));
            $log = (string) file_get_contents($path);
            self::assertStringContainsString('health reporter failed', $log);
            self::assertStringNotContainsString('secret-reporter-message', $log);
            self::assertCount(0, $sink->published());
        } finally {
            ini_set('error_log', $previous);
            unlink($path);
        }
    }

    public function testTraceSeverityIsRetainedWithoutUnclassifiedContent(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $classifier = new DefaultObservationClassifier($sink, null, new SecurityLimits());
        $classifier->publish(new VerifiedArtifactProjection([
            'id' => 'export-observation', 'execution_id' => 'export-execution',
            'severity' => 'trace', 'timestamp' => 1, 'sequence' => 1,
            'message' => 'secret-message', 'attributes' => ['secret' => 'value'],
        ], 'observation'));

        self::assertCount(1, $sink->published());
        $fields = $sink->published()[0]->normalize();
        self::assertSame('trace', $fields['severity']);
        self::assertSame(50, $fields['level']);
        self::assertStringNotContainsString('secret', json_encode($fields, JSON_THROW_ON_ERROR));
    }

    public function testTypedTraceIsSanitizedBeforeSeverityFloorSampling(): void
    {
        $input = new ClassifiedTraceObservation(Observation::debug(
            ObservationId::fromString('export-observation'),
            ExecutionId::fromString('export-execution'),
            'secret-message',
            attributes: AttributeCollection::fromArray(['secret' => 'value']),
        ));
        $policy = new class () implements SamplingPolicyInterface {
            public ?ExecutionArtifactInterface $seen = null;

            public function sample(ExecutionArtifactInterface $artifact): bool
            {
                $this->seen = $artifact;
                return new SeveritySamplePolicy(ObservationSeverity::LEVEL_DEBUG)->sample($artifact);
            }
        };
        $sink = new InMemoryArtifactPublisher();
        $classifier = new DefaultObservationClassifier(
            new SamplingArtifactPublisher($policy, $sink),
            null,
            new SecurityLimits(),
        );
        $classifier->publish($input);

        self::assertInstanceOf(ObservationInterface::class, $policy->seen);
        self::assertSame(ObservationSeverity::Trace, $policy->seen->severity());
        self::assertSame('[redacted]', $policy->seen->message());
        self::assertSame([], $policy->seen->attributes());
        self::assertStringNotContainsString('secret', json_encode($policy->seen->normalize(), JSON_THROW_ON_ERROR));
        self::assertCount(0, $sink->published());
        self::assertTrue(new SeveritySamplePolicy(ObservationSeverity::LEVEL_TRACE)->sample($policy->seen));
    }

    public function testThrowableRetainsOnlyBoundedClassAndCode(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $classifier = new DefaultObservationClassifier($sink, null, new SecurityLimits());
        $classifier->publish(new RedactedArtifact([
            'id' => 'export-observation', 'execution_id' => 'export-execution',
            'severity' => 'error', 'timestamp' => 1, 'sequence' => 1,
            'throwable' => ['class' => 'RuntimeException', 'code' => 42, 'message' => 'secret',
                'trace' => 'secret trace', 'extra' => 'secret', 'previous' => [
                    'class' => 'InvalidArgumentException', 'code' => 3, 'message' => 'secret',
                ]],
        ], 'observation'));

        self::assertCount(1, $sink->published());
        $fields = $sink->published()[0]->normalize();
        self::assertIsArray($fields['throwable']);
        self::assertSame('RuntimeException', $fields['throwable']['class']);
        self::assertSame(42, $fields['throwable']['code']);
        self::assertIsArray($fields['throwable']['previous']);
        self::assertSame('InvalidArgumentException', $fields['throwable']['previous']['class']);
        self::assertStringNotContainsString('secret', json_encode($fields, JSON_THROW_ON_ERROR));
    }

    public function testLowAttributeLimitsReportAndDropRatherThanTruncateThrowable(): void
    {
        foreach (range(1, 4) as $limit) {
            $reports = [];
            $health = new CallbackHealthReporter(static function (
                Throwable $error,
                ExecutionArtifactInterface $artifact,
            ) use (&$reports): void {
                $reports[] = [$error->getMessage(), $artifact->normalize()];
            });
            $sink = new InMemoryArtifactPublisher();
            $classifier = new DefaultObservationClassifier(
                $sink,
                $health,
                new SecurityLimits(maxAttributeCount: $limit),
            );
            $classifier->publish(new VerifiedArtifactProjection([
                'id' => 'export-observation', 'execution_id' => 'export-execution',
                'severity' => 'error', 'timestamp' => 1, 'sequence' => 1,
                'message' => 'secret', 'throwable' => [
                    'class' => 'RuntimeException', 'code' => 42, 'message' => 'secret',
                    'previous' => ['class' => 'InvalidArgumentException', 'code' => 7],
                ],
            ], 'observation'));

            self::assertCount(0, $sink->published(), 'limit ' . $limit);
            self::assertCount(1, $reports, 'limit ' . $limit);
            self::assertStringNotContainsString('secret', json_encode($reports, JSON_THROW_ON_ERROR));
        }
    }
}
