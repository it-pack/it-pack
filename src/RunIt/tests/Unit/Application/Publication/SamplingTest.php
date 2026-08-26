<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\AlwaysSamplePolicy;
use ItPack\RunIt\Application\Publication\ErrorOverrideSamplePolicy;
use ItPack\RunIt\Application\Publication\ExecutionKindSamplePolicy;
use ItPack\RunIt\Application\Publication\HeadBasedSamplingPolicy;
use ItPack\RunIt\Application\Publication\NeverSamplePolicy;
use ItPack\RunIt\Application\Publication\RatioSamplePolicy;
use ItPack\RunIt\Application\Publication\SamplingArtifactPublisher;
use ItPack\RunIt\Application\Publication\SeveritySamplePolicy;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Shared\SecurityLimits;

/**
 * @group application
 * @group publication
 */
final class SamplingTest extends Unit
{
    public function testHeadBasedSamplingFallsBackToAnEmptyKeyWhenTheArtifactHasNoStringId(): void
    {
        $inner = new NeverSamplePolicy();
        $policy = new HeadBasedSamplingPolicy($inner);

        $idless = new class () implements \ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface {
            public function normalize(): array
            {
                return ['sequence' => 1];
            }
        };

        self::assertFalse($policy->sample($idless), 'an id-less artifact keys on an empty string and follows the inner policy');
    }

    private function observation(string $severity = 'info', string $execution = 'exec-1'): ExecutionArtifactInterface
    {
        $id = ObservationId::fromString('obs');
        $executionId = ExecutionId::fromString($execution);

        return match ($severity) {
            'debug' => Observation::debug($id, $executionId, 'msg'),
            'info' => Observation::info($id, $executionId, 'msg'),
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
        self::assertTrue(new AlwaysSamplePolicy()->sample($this->observation()));
        self::assertFalse(new NeverSamplePolicy()->sample($this->observation()));
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
        $policy = new RatioSamplePolicy(0.5, static fn (): float => (float) (mt_rand() / mt_getrandmax()));
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
}
