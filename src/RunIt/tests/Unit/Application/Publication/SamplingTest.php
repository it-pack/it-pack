<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Factory\PublisherStackFactory;
use ItPack\RunIt\Application\Publication\AlwaysSamplePolicy;
use ItPack\RunIt\Application\Publication\ArtifactRedactionConfig;
use ItPack\RunIt\Application\Publication\DefaultArtifactRedactor;
use ItPack\RunIt\Application\Publication\ErrorOverrideSamplePolicy;
use ItPack\RunIt\Application\Publication\ExecutionKindSamplePolicy;
use ItPack\RunIt\Application\Publication\HeadBasedSamplingPolicy;
use ItPack\RunIt\Application\Publication\NeverSamplePolicy;
use ItPack\RunIt\Application\Publication\RatioSamplePolicy;
use ItPack\RunIt\Application\Publication\RedactedArtifact;
use ItPack\RunIt\Application\Publication\RedactingArtifactPublisher;
use ItPack\RunIt\Application\Publication\SamplingArtifactPublisher;
use ItPack\RunIt\Application\Publication\SeveritySamplePolicy;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\RedactedArtifactInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Throwable;

/**
 * @group application
 * @group publication
 */
#[CoversClass(PublisherStackFactory::class)]
#[CoversClass(AlwaysSamplePolicy::class)]
#[CoversClass(ErrorOverrideSamplePolicy::class)]
#[CoversClass(ExecutionKindSamplePolicy::class)]
#[CoversClass(HeadBasedSamplingPolicy::class)]
#[CoversClass(NeverSamplePolicy::class)]
#[CoversClass(RatioSamplePolicy::class)]
#[CoversClass(SamplingArtifactPublisher::class)]
#[CoversClass(SeveritySamplePolicy::class)]
#[UsesClass(InMemoryArtifactPublisher::class)]
#[UsesClass(ArtifactRedactionConfig::class)]
#[UsesClass(DefaultArtifactRedactor::class)]
#[UsesClass(RedactedArtifact::class)]
#[UsesClass(RedactingArtifactPublisher::class)]
#[UsesClass(ExecutionId::class)]
#[UsesClass(ExecutionKind::class)]
#[UsesClass(Observation::class)]
#[UsesClass(ObservationId::class)]
#[UsesClass(AttributeCollection::class)]
#[UsesClass(SecurityLimits::class)]
final class SamplingTest extends Unit
{
    public function testHeadBasedSamplingFallsBackToAnEmptyKeyWhenTheArtifactHasNoStringId(): void
    {
        $inner = new NeverSamplePolicy();
        $policy = new HeadBasedSamplingPolicy($inner);

        $idless = new class () implements ExecutionArtifactInterface {
            public function normalize(): array
            {
                return ['sequence' => 1];
            }
        };

        self::assertFalse(
            $policy->sample($idless),
            'an id-less artifact keys on an empty string and follows the inner policy'
        );
    }

    private function observation(string $severity = 'info', string $execution = 'exec-1'): ExecutionArtifactInterface
    {
        $id = ObservationId::fromString('obs');
        $executionId = ExecutionId::fromString($execution);

        return match ($severity) {
            'debug' => Observation::debug($id, $executionId, 'msg'),
            'notice' => Observation::notice($id, $executionId, 'msg'),
            'warning' => Observation::warning($id, $executionId, 'msg'),
            'error' => Observation::error($id, $executionId, 'msg'),
            'critical' => Observation::critical($id, $executionId, 'msg'),
            'alert' => Observation::alert($id, $executionId, 'msg'),
            'emergency' => Observation::emergency($id, $executionId, 'msg'),
            default => Observation::info($id, $executionId, 'msg'),
        };
    }

    public function testAlwaysAndNever(): void
    {
        $alwaysResult = new AlwaysSamplePolicy()->sample($this->observation());
        $neverResult = new NeverSamplePolicy()->sample($this->observation());
        self::assertTrue($alwaysResult);
        self::assertFalse($neverResult);
    }

    public function testRatioIsDeterministic(): void
    {
        self::assertTrue(new RatioSamplePolicy(0.5, static fn (): float => 0.1)->sample($this->observation()));
        self::assertFalse(new RatioSamplePolicy(0.5, static fn (): float => 0.9)->sample($this->observation()));
        self::assertFalse(new RatioSamplePolicy(0.0)->sample($this->observation()));
        self::assertTrue(new RatioSamplePolicy(1.0)->sample($this->observation()));
    }

    public function testRatioApproximatesOverVolume(): void
    {
        $policy = new RatioSamplePolicy(0.5);
        $kept = 0;
        $total = 4000;

        for ($i = 0; $i < $total; $i++) {
            if ($policy->sample($this->observation())) {
                $kept++;
            }
        }

        $rate = $kept / $total;
        self::assertGreaterThan(0.40, $rate);
        self::assertLessThan(0.60, $rate);
    }

    public function testExecutionKindPolicy(): void
    {
        $resolver = static fn (ExecutionIdInterface $id): ?ExecutionKind => match ($id->toString()) {
            'exec-1' => ExecutionKind::fromString('http.request'),
            default => null,
        };

        $policy = new ExecutionKindSamplePolicy($resolver, ['http.request' => true, 'cli.command' => false]);

        self::assertTrue($policy->sample($this->observation('info', 'exec-1')));
        self::assertFalse($policy->sample($this->observation('info', 'exec-2')));
    }

    public function testSeverityPolicyKeepsErrorsAndDropsNoise(): void
    {
        $policy = new SeveritySamplePolicy(ObservationSeverity::Warning->level());

        self::assertFalse($policy->sample($this->observation('debug')));
        self::assertFalse($policy->sample($this->observation('info')));
        self::assertTrue($policy->sample($this->observation('warning')));
        self::assertTrue($policy->sample($this->observation('error')));
    }

    public function testErrorOverrideWinsOverOrdinaryPolicy(): void
    {
        $policy = new ErrorOverrideSamplePolicy(new NeverSamplePolicy());

        self::assertTrue($policy->sample($this->observation('error')));
        self::assertFalse($policy->sample($this->observation('info')));
    }

    public function testHeadBasedPinsDecisionPerExecution(): void
    {
        $inner = new class () implements SamplingPolicyInterface {
            public int $calls = 0;

            public function sample(ExecutionArtifactInterface $artifact): bool
            {
                $this->calls++;

                return $this->calls === 1;
            }
        };

        $policy = new HeadBasedSamplingPolicy($inner);

        $first = $policy->sample($this->observation('info', 'exec-1'));
        $second = $policy->sample($this->observation('info', 'exec-1'));

        self::assertTrue($first);
        self::assertTrue($second, 'The second artifact follows the first execution decision (head-based).');
        self::assertSame(1, $inner->calls, 'The wrapped policy is consulted only once per execution.');
    }

    public function testDecisionCacheIsBoundedAndOldestKeyIsReDecided(): void
    {
        $inner = new class () implements SamplingPolicyInterface {
            public int $calls = 0;

            public function sample(ExecutionArtifactInterface $artifact): bool
            {
                $this->calls++;

                return true;
            }
        };

        $policy = new HeadBasedSamplingPolicy(
            $inner,
            limits: new SecurityLimits(maxTrackedExecutions: 1),
        );

        $policy->sample($this->observation('info', 'exec-1'));
        self::assertSame(1, $inner->calls);

        // A second execution evicts the first key from the bounded cache...
        $policy->sample($this->observation('info', 'exec-2'));
        self::assertSame(2, $inner->calls);

        // ...so the first execution is re-decided instead of being retained forever.
        $policy->sample($this->observation('info', 'exec-1'));
        self::assertSame(3, $inner->calls, 'an evicted execution key must be re-decided through the wrapped policy');
    }

    /**
     * @throws Throwable
     */
    public function testSamplingPublisherDropsRejectedArtifacts(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $publisher = new SamplingArtifactPublisher(new NeverSamplePolicy(), $sink);

        $publisher->publish($this->observation('info'));
        $publisher->publish($this->observation('error'));

        self::assertCount(0, $sink->published());

        $keep = new SamplingArtifactPublisher(new AlwaysSamplePolicy(), new InMemoryArtifactPublisher());
        $keepSink = new InMemoryArtifactPublisher();
        $keep = new SamplingArtifactPublisher(new AlwaysSamplePolicy(), $keepSink);
        $keep->publish($this->observation('info'));

        self::assertCount(1, $keepSink->published());
    }

    /**
     * @throws Throwable
     */
    public function testFactorySamplesRedactedArtifactsAfterRedaction(): void
    {
        $policy = new class () implements SamplingPolicyInterface {
            /** @var list<ExecutionArtifactInterface> */
            public array $seen = [];

            public function sample(ExecutionArtifactInterface $artifact): bool
            {
                $this->seen[] = $artifact;

                return true;
            }
        };
        $sink = new InMemoryArtifactPublisher();
        $redactor = new DefaultArtifactRedactor(new ArtifactRedactionConfig(
            sensitiveAttributeKeys: ['secret'],
            redactObservationMessages: true,
        ));
        $publisher = new PublisherStackFactory()->compose($sink, $redactor, $policy);
        $attributes = AttributeCollection::fromArray(['secret' => 'sensitive']);
        $id = ExecutionId::fromString('exec-1');
        $changed = Observation::info(ObservationId::fromString('changed'), $id, 'keep', attributes: $attributes);
        $rejected = Observation::info(ObservationId::fromString('rejected'), $id, 'reject', attributes: $attributes);
        $unchanged = $this->observation();

        $publisher->publish($changed);
        $publisher->publish($rejected);
        $publisher->publish($unchanged);

        // The sampler sees redacted artifacts, not originals.
        self::assertCount(3, $policy->seen);
        self::assertSame(['secret' => '[redacted]'], $policy->seen[0]->normalize()['attributes']);
        self::assertSame(['secret' => '[redacted]'], $policy->seen[1]->normalize()['attributes']);
        self::assertSame([], $policy->seen[2]->normalize()['attributes']);
        self::assertSame('[redacted]', $policy->seen[2]->normalize()['message']);

        // The sink receives redacted artifacts.
        self::assertCount(3, $sink->published());
        self::assertNotSame($changed, $sink->published()[0]);
        self::assertSame(['secret' => '[redacted]'], $sink->published()[0]->normalize()['attributes']);
        self::assertSame('[redacted]', $sink->published()[0]->normalize()['message']);
        self::assertNotSame($rejected, $sink->published()[1]);
        self::assertNotSame($unchanged, $sink->published()[2]);
    }

    public function testFactoryHandlesNullDecorators(): void
    {
        $factory = new PublisherStackFactory();
        $sink = new InMemoryArtifactPublisher();
        $artifact = $this->observation();
        self::assertSame($sink, $factory->compose($sink, null, null));

        $sampling = new class () implements SamplingPolicyInterface {
            /** @var list<ExecutionArtifactInterface> */
            public array $seen = [];

            public function sample(ExecutionArtifactInterface $artifact): bool
            {
                $this->seen[] = $artifact;

                return true;
            }
        };
        $factory->compose($sink, null, $sampling)->publish($artifact);
        self::assertSame([$artifact], $sampling->seen);
        self::assertSame([$artifact], $sink->published());

        $changed = Observation::info(
            ObservationId::fromString('secret'),
            ExecutionId::fromString('exec-1'),
            'message',
            attributes: AttributeCollection::fromArray(['secret' => 'sensitive']),
        );
        $redactor = new DefaultArtifactRedactor(new ArtifactRedactionConfig(
            sensitiveAttributeKeys: ['secret'],
            redactObservationMessages: true,
        ));
        $redactedSink = new InMemoryArtifactPublisher();
        $factory->compose($redactedSink, $redactor, null)->publish($changed);
        self::assertCount(1, $redactedSink->published());
        self::assertNotSame($changed, $redactedSink->published()[0]);
        self::assertSame(['secret' => '[redacted]'], $redactedSink->published()[0]->normalize()['attributes']);
    }

    /**
     * @throws Throwable
     */
    public function testFactorySamplesObservationSeverityAfterRedaction(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $redactor = new DefaultArtifactRedactor(new ArtifactRedactionConfig(
            sensitiveAttributeKeys: ['secret'],
            redactObservationMessages: true,
        ));
        $policy = new SeveritySamplePolicy(ObservationSeverity::Warning->level());
        $publisher = new PublisherStackFactory()->compose($sink, $redactor, $policy);
        $attributes = AttributeCollection::fromArray(['secret' => 'sensitive']);
        $id = ExecutionId::fromString('exec-1');
        $low = Observation::info(ObservationId::fromString('low'), $id, 'low', attributes: $attributes);
        $high = Observation::warning(ObservationId::fromString('high'), $id, 'high', attributes: $attributes);

        // A redacted observation carries its level in normalized data, so the
        // severity floor applies to redacted artifacts as well.
        self::assertFalse($policy->sample($redactor->redact($low)), 'Redacted info is below the warning floor.');
        self::assertTrue($policy->sample($redactor->redact($high)), 'Redacted warning meets the warning floor.');
        $publisher->publish($low);
        $publisher->publish($high);

        self::assertCount(1, $sink->published());
        self::assertSame('[redacted]', $sink->published()[0]->normalize()['message']);
        self::assertSame(['secret' => '[redacted]'], $sink->published()[0]->normalize()['attributes']);
    }

    /**
     * @throws Throwable
     */
    public function testFactorySamplesErrorOverrideAfterRedaction(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $redactor = new DefaultArtifactRedactor(new ArtifactRedactionConfig(
            sensitiveAttributeKeys: ['secret'],
            redactObservationMessages: true,
        ));
        $policy = new ErrorOverrideSamplePolicy(new NeverSamplePolicy());
        $publisher = new PublisherStackFactory()->compose($sink, $redactor, $policy);
        $id = ExecutionId::fromString('exec-1');
        $attributes = AttributeCollection::fromArray(['secret' => 'sensitive']);
        $error = Observation::error(ObservationId::fromString('error'), $id, 'error', attributes: $attributes);
        $info = Observation::info(ObservationId::fromString('info'), $id, 'info', attributes: $attributes);
        $unchanged = $this->observation('error');
        $redacted = $redactor->redact($error);

        // Error override applies to redacted observations via normalized level.
        self::assertTrue($policy->sample($error));
        self::assertTrue($policy->sample($redacted));
        self::assertNotSame($unchanged, $redactor->redact($unchanged));
        self::assertSame('[redacted]', $redactor->redact($unchanged)->normalize()['message']);
        $publisher->publish($error);
        $publisher->publish($info);
        $publisher->publish($unchanged);

        self::assertCount(2, $sink->published());
        self::assertNotSame($error, $sink->published()[0]);
        self::assertSame(['secret' => '[redacted]'], $sink->published()[0]->normalize()['attributes']);
        self::assertNotSame($unchanged, $sink->published()[1]);
        self::assertSame('[redacted]', $sink->published()[1]->normalize()['message']);
    }

    /**
     * @throws Throwable
     */
    public function testFactorySamplesExecutionKindAfterRedaction(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $redactor = new DefaultArtifactRedactor(new ArtifactRedactionConfig(
            sensitiveAttributeKeys: ['secret'],
            redactObservationMessages: true,
        ));
        $policy = new ExecutionKindSamplePolicy(
            static fn (ExecutionIdInterface $id): ?ExecutionKind => match ($id->toString()) {
                'exec-1' => ExecutionKind::fromString('http.request'),
                'exec-2' => ExecutionKind::fromString('cli.command'),
                default => null,
            },
            ['http.request' => true, 'cli.command' => false],
        );
        $publisher = new PublisherStackFactory()->compose($sink, $redactor, $policy);
        $attributes = AttributeCollection::fromArray(['secret' => 'sensitive']);
        $kept = Observation::info(
            ObservationId::fromString('kept'),
            ExecutionId::fromString('exec-1'),
            'kept',
            attributes: $attributes,
        );
        $dropped = Observation::info(
            ObservationId::fromString('dropped'),
            ExecutionId::fromString('exec-2'),
            'dropped',
            attributes: $attributes,
        );
        $unknown = $this->observation('info', 'exec-3');
        $unchanged = $this->observation();

        // Execution kind resolves from redacted normalized execution_id.
        self::assertTrue($policy->sample($kept));
        self::assertTrue($policy->sample($redactor->redact($kept)));
        self::assertFalse($policy->sample($redactor->redact($dropped)));
        self::assertNotSame($unchanged, $redactor->redact($unchanged));
        self::assertSame('[redacted]', $redactor->redact($unchanged)->normalize()['message']);
        $publisher->publish($kept);
        $publisher->publish($dropped);
        $publisher->publish($unknown);
        $publisher->publish($unchanged);

        self::assertCount(2, $sink->published());
        self::assertNotSame($kept, $sink->published()[0]);
        self::assertSame(['secret' => '[redacted]'], $sink->published()[0]->normalize()['attributes']);
        self::assertNotSame($unchanged, $sink->published()[1]);
        self::assertSame('[redacted]', $sink->published()[1]->normalize()['message']);
    }

    /** Redacting an already-redacted artifact preserves the first projection's trusted artifact type. */
    public function testRedactingRedactedArtifactPreservesArtifactType(): void
    {
        $redactor = new DefaultArtifactRedactor(new ArtifactRedactionConfig(
            sensitiveAttributeKeys: ['secret'],
        ));
        $observation = Observation::info(
            ObservationId::fromString('obs-1'),
            ExecutionId::fromString('exec-1'),
            'kept',
            attributes: AttributeCollection::fromArray(['secret' => 'sensitive']),
        );

        $first = $redactor->redact($observation);
        self::assertInstanceOf(RedactedArtifactInterface::class, $first);
        self::assertSame('observation', $first->artifactType());

        $second = $redactor->redact($first);
        self::assertInstanceOf(RedactedArtifactInterface::class, $second);
        self::assertSame($first->artifactType(), $second->artifactType());
        self::assertSame('observation', $second->artifactType());
    }
}
