<?php

declare(strict_types=1);

namespace Tests\Unit\SDK\Builder;

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\DefaultArtifactRedactor;
use ItPack\RunIt\Application\Publication\ExecutionKindSamplePolicy;
use ItPack\RunIt\Application\Publication\VerifiedArtifactProjection;
use ItPack\RunIt\Contract\Application\ActivityScopeInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\ActivityInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Domain\Activity\ActivityDefinition;
use ItPack\RunIt\Domain\Activity\ActivityKind;
use ItPack\RunIt\Domain\Activity\ActivityName;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\Builder\RunItBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

#[CoversClass(RunItBuilder::class)]
final class LifecycleSecurityTest extends TestCase
{
    public function testDefaultAndExplicitRedactorsAtSamplerAndSink(): void
    {
        foreach (['default', 'null', 'custom'] as $mode) {
            $sink = new InMemoryArtifactPublisher();
            $health = [];
            $sampler = new class () implements SamplingPolicyInterface {
                /** @var list<ExecutionArtifactInterface> */
                public array $seen = [];

                public function sample(ExecutionArtifactInterface $artifact): bool
                {
                    $this->seen[] = $artifact;
                    return true;
                }
            };
            $builder = RunItBuilder::create()->withPublisher($sink)
                ->withClock(new SystemMonotonicClock())->withSequenceAllocator(new InMemorySequenceAllocator())
                ->withSnapshotSerializer(new JsonSnapshotSerializer())
                ->withIdentifierGenerator(new RandomIdentifierGenerator())
                ->withSamplingPolicy($sampler)
                ->withPublicationFailureHandler(static function (Throwable $error) use (&$health): void {
                    $health[] = $error->getMessage();
                })
            ;
            if ($mode !== 'default') {
                $builder = $builder->withRedactor($mode === 'null' ? null : new DefaultArtifactRedactor());
            }

            $business = [];
            $builder->build()->run(
                ExecutionDefinition::create(
                    ExecutionName::fromString('secret-execution'),
                    ExecutionKind::fromString('secret-kind')
                ),
                static function (ExecutionScopeInterface $scope) use (&$business): void {
                    $business[] = $scope->execution()->id()->toString();
                    $scope->activities()->run(
                        ActivityDefinition::create(
                            ActivityName::fromString('secret-outer'),
                            ActivityKind::fromString('secret-kind')
                        ),
                        static function (ActivityScopeInterface $outer) use (&$business): void {
                            $business[] = $outer->activity()->id()->toString();
                            $outer->activities()->run(
                                ActivityDefinition::create(
                                    ActivityName::fromString('secret-inner'),
                                    ActivityKind::fromString('secret-kind'),
                                    $outer->activity()->id(),
                                ),
                                static fn (): null => null,
                            );
                        },
                    );
                },
            );

            foreach ([$sampler->seen, $sink->published()] as $artifacts) {
                $executions = array_values(array_filter($artifacts, static fn (ExecutionArtifactInterface $a): bool =>
                    $a instanceof ExecutionInterface
                    || ($a instanceof VerifiedArtifactProjection && $a->artifactType() === 'execution')));
                $activities = array_values(array_filter($artifacts, static fn (ExecutionArtifactInterface $a): bool =>
                    $a instanceof ActivityInterface
                    || ($a instanceof VerifiedArtifactProjection && $a->artifactType() === 'activity')));
                self::assertNotEmpty($executions, $mode);
                self::assertGreaterThanOrEqual(2, count($activities), $mode);
                $execution = $executions[0]->normalize();
                $outer = $activities[0]->normalize();
                $inner = $activities[1]->normalize();
                self::assertSame($execution['id'], $outer['execution_id']);
                self::assertSame($execution['id'], $inner['execution_id']);
                self::assertSame($outer['id'], $inner['parent_activity_id']);
                self::assertNotSame($business[0], $execution['id']);
                self::assertNotSame($business[1], $outer['id']);
                $encoded = json_encode([$execution, $outer, $inner], JSON_THROW_ON_ERROR);
                foreach ([$execution, $outer, $inner] as $fields) {
                    self::assertSame('runit.redacted', $fields['name'], $mode);
                    self::assertSame('runit.redacted', $fields['kind'], $mode);
                }

                self::assertStringNotContainsString('secret-', $encoded, $mode);
                self::assertStringNotContainsString($business[0], $encoded, $mode);
                self::assertStringNotContainsString($business[1], $encoded, $mode);
                foreach (array_merge($executions, $activities) as $artifact) {
                    $fields = $artifact->normalize();
                    self::assertSame('runit.redacted', $fields['name'], $mode);
                    self::assertSame('runit.redacted', $fields['kind'], $mode);
                    self::assertStringNotContainsString('secret-', json_encode($fields, JSON_THROW_ON_ERROR), $mode);
                }
            }

            self::assertSame([], $health, $mode);
        }
    }

    public function testExecutionKindPolicyResolvesExportIdentity(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $resolved = [];
        $policy = new ExecutionKindSamplePolicy(
            static function (ExecutionIdInterface $id) use (&$resolved): ExecutionKind {
                $resolved[] = $id->toString();
                return ExecutionKind::fromString('allowed');
            },
            ['allowed' => true],
        );
        $runtime = RunItBuilder::create()->withPublisher($sink)
            ->withClock(new SystemMonotonicClock())->withSequenceAllocator(new InMemorySequenceAllocator())
            ->withSnapshotSerializer(new JsonSnapshotSerializer())
            ->withIdentifierGenerator(new RandomIdentifierGenerator())
            ->withSamplingPolicy($policy)->build()
        ;
        $runtime->run(
            ExecutionDefinition::create(
                ExecutionName::fromString('secret-name'),
                ExecutionKind::fromString('secret-kind')
            ),
            static fn (): null => null,
        );
        self::assertNotEmpty($resolved);
        $executions = array_values(array_filter(
            $sink->published(),
            static fn (ExecutionArtifactInterface $a): bool => $a instanceof ExecutionInterface
        ));
        self::assertNotEmpty($executions);
        $fields = $executions[0]->normalize();
        self::assertContains($fields['id'], $resolved);
        self::assertSame('runit.redacted', $fields['kind']);
    }

    public function testFailedLifecycleArtifactsHideMessagesBeforeSamplingAndAtSink(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $sampler = new class () implements SamplingPolicyInterface {
            /** @var list<ExecutionArtifactInterface> */
            public array $seen = [];

            public function sample(ExecutionArtifactInterface $artifact): bool
            {
                $this->seen[] = $artifact;
                return true;
            }
        };
        $runtime = RunItBuilder::create()->withPublisher($sink)
            ->withClock(new SystemMonotonicClock())->withSequenceAllocator(new InMemorySequenceAllocator())
            ->withSnapshotSerializer(new JsonSnapshotSerializer())
            ->withIdentifierGenerator(new RandomIdentifierGenerator())->withSamplingPolicy($sampler)->build()
        ;
        try {
            $runtime->run(
                ExecutionDefinition::create(ExecutionName::fromString('test'), ExecutionKind::fromString('test')),
                static function (ExecutionScopeInterface $scope): void {
                    $scope->activities()->run(
                        ActivityDefinition::create(
                            ActivityName::fromString('activity'),
                            ActivityKind::fromString('task')
                        ),
                        static function (): never {
                            throw new RuntimeException('secret-message', 42);
                        },
                    );
                },
            );
            self::fail('Expected the original business exception.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('secret-message', $runtimeException->getMessage());
        }

        foreach ([$sampler->seen, $sink->published()] as $artifacts) {
            $executions = array_values(array_filter($artifacts, static fn (ExecutionArtifactInterface $a): bool =>
                $a instanceof ExecutionInterface && $a->status()->label() === 'failed'));
            $activities = array_values(array_filter($artifacts, static fn (ExecutionArtifactInterface $a): bool =>
                $a instanceof ActivityInterface && $a->status()->label() === 'failed'));
            self::assertCount(1, $executions);
            self::assertCount(1, $activities);
            $result = $executions[0]->normalize()['result'];
            $throwable = $activities[0]->normalize()['throwable'];
            self::assertIsArray($result);
            self::assertIsArray($result['throwable']);
            self::assertIsArray($throwable);
            self::assertSame('RuntimeException', $result['throwable']['class']);
            self::assertSame(42, $throwable['code']);
            self::assertStringNotContainsString(
                'secret-message',
                json_encode([$result, $throwable], JSON_THROW_ON_ERROR)
            );
        }
    }
}
