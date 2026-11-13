<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\CallbackHealthReporter;
use ItPack\RunIt\Application\Publication\DefaultLifecycleClassifier;
use ItPack\RunIt\Application\Publication\VerifiedArtifactProjection;
use ItPack\RunIt\Contract\Domain\ActivityInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Throwable;

#[CoversClass(DefaultLifecycleClassifier::class)]
#[UsesClass(InMemoryArtifactPublisher::class)]
#[UsesClass(CallbackHealthReporter::class)]
#[UsesClass(VerifiedArtifactProjection::class)]
final class DefaultLifecycleClassifierTest extends Unit
{
    public function testExecutionAndActivityHaveOnlySafeFields(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $classifier = new DefaultLifecycleClassifier($sink, null, new SecurityLimits());
        $execution = ['id' => 'export-execution', 'name' => 'secret-name', 'kind' => 'secret-kind',
            'status' => 'succeeded', 'started_at' => 1, 'ended_at' => 2, 'sequence' => 3,
            'parent_execution_id' => 'export-parent', 'linked_execution_ids' => ['export-link'],
            'context' => ['secret-key' => 'secret-value'], 'result' => ['secret-result']];
        $activity = ['id' => 'export-activity', 'execution_id' => 'export-execution', 'name' => 'secret-name',
            'kind' => 'secret-kind', 'status' => 'failed', 'started_at' => 1, 'ended_at' => 2,
            'sequence' => 4, 'parent_activity_id' => 'export-parent-activity', 'duration_ns' => 9,
            'attributes' => ['secret-key' => 'secret-value'], 'throwable' => ['message' => 'secret-error']];
        $classifier->publish(new VerifiedArtifactProjection($execution, 'execution'));
        $classifier->publish(new VerifiedArtifactProjection($activity, 'activity'));
        [$safeExecution, $safeActivity] = $sink->published();

        self::assertInstanceOf(ExecutionInterface::class, $safeExecution);
        self::assertInstanceOf(ActivityInterface::class, $safeActivity);
        self::assertSame('export-parent', $safeExecution->normalize()['parent_execution_id']);
        self::assertSame(['export-link'], $safeExecution->normalize()['linked_execution_ids']);
        self::assertSame('export-parent-activity', $safeActivity->normalize()['parent_activity_id']);
        self::assertSame(9, $safeActivity->normalize()['duration_ns']);
        self::assertSame('failed', $safeActivity->normalize()['status']);
        self::assertStringNotContainsString(
            'secret',
            json_encode([$safeExecution->normalize(), $safeActivity->normalize()], JSON_THROW_ON_ERROR)
        );
    }

    public function testInvalidFieldsDropWithoutExposingPayloadAndOtherKindsPassThrough(): void
    {
        $reports = [];
        $health = new CallbackHealthReporter(static function (
            Throwable $error,
            ExecutionArtifactInterface $artifact,
        ) use (&$reports): void {
            $reports[] = [$error->getMessage(), $artifact->normalize()];
        });
        $sink = new InMemoryArtifactPublisher();
        $classifier = new DefaultLifecycleClassifier($sink, $health, new SecurityLimits());
        $classifier->publish(new VerifiedArtifactProjection(['id' => 'secret', 'status' => 'secret'], 'execution'));

        $other = new VerifiedArtifactProjection(['id' => 'other'], 'observation');
        $classifier->publish($other);

        self::assertSame([$other], $sink->published());
        self::assertSame([['Default lifecycle classification failed.', [
            'id' => 'redacted', 'type' => 'runit.artifact.redacted',
        ]]], $reports);
    }

    public function testNestedThrowableMetadataSurvivesInTypedExecutionAndActivity(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $classifier = new DefaultLifecycleClassifier($sink, null, new SecurityLimits());
        $cause = ['class' => 'Vendor\\Cause', 'code' => 7, 'message' => 'secret-cause', 'trace' => 'secret-trace'];
        $throwable = ['class' => 'RuntimeException', 'code' => 42, 'message' => 'secret-message',
            'trace' => 'secret-trace', 'previous' => $cause, 'extra' => 'secret-extra'];
        $base = ['id' => 'export-execution', 'status' => 'failed', 'started_at' => 1, 'ended_at' => 2,
            'sequence' => 3, 'parent_execution_id' => null, 'linked_execution_ids' => [],
            'result' => ['status' => 'failed', 'throwable' => $throwable, 'extra' => 'secret-result']];
        $activity = ['id' => 'export-activity', 'execution_id' => 'export-execution', 'status' => 'failed',
            'started_at' => 1, 'ended_at' => 2, 'sequence' => 4, 'parent_activity_id' => null,
            'throwable' => $throwable];
        $classifier->publish(new VerifiedArtifactProjection($base, 'execution'));
        $classifier->publish(new VerifiedArtifactProjection($activity, 'activity'));
        [$execution, $classifiedActivity] = $sink->published();
        $expected = ['class' => 'RuntimeException', 'message' => '[redacted]', 'code' => 42,
            'previous' => ['class' => 'Vendor\\Cause', 'message' => '[redacted]', 'code' => 7,
                'previous' => null, 'trace' => null], 'trace' => null];

        self::assertInstanceOf(ExecutionInterface::class, $execution);
        self::assertInstanceOf(ActivityInterface::class, $classifiedActivity);
        self::assertSame(['status' => 'failed', 'throwable' => $expected], $execution->normalize()['result']);
        self::assertSame($expected, $classifiedActivity->normalize()['throwable']);
        self::assertStringNotContainsString(
            'secret',
            json_encode([$execution->normalize(), $classifiedActivity->normalize()], JSON_THROW_ON_ERROR)
        );
    }

    public function testInvalidThrowableAndLongCauseChainsAreOmittedSafely(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $classifier = new DefaultLifecycleClassifier($sink, null, new SecurityLimits());
        $chain = null;
        for ($i = 0; $i < 20; ++$i) {
            $chain = ['class' => 'RuntimeException', 'code' => $i, 'previous' => $chain];
        }

        $base = ['id' => 'export-activity', 'execution_id' => 'export-execution', 'status' => 'failed',
            'started_at' => 1, 'ended_at' => 2, 'sequence' => 4, 'parent_activity_id' => null];
        $classifier->publish(new VerifiedArtifactProjection($base + ['throwable' => $chain], 'activity'));
        $classifier->publish(new VerifiedArtifactProjection($base + ['throwable' => [
            'class' => 'secret invalid class', 'code' => 1,
        ]], 'activity'));
        [$first, $second] = $sink->published();
        $node = $first->normalize()['throwable'];
        $count = 0;
        while (is_array($node)) {
            ++$count;
            $node = $node['previous'];
        }

        self::assertSame(12, $count);
        self::assertNull($second->normalize()['throwable']);
    }

    public function testTerminalResultRetainsStatusWhenThrowableIsInvalid(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $classifier = new DefaultLifecycleClassifier($sink, null, new SecurityLimits());
        $classifier->publish(new VerifiedArtifactProjection([
            'id' => 'export-execution', 'status' => 'cancelled', 'started_at' => 1, 'ended_at' => 2,
            'sequence' => 3, 'parent_execution_id' => null, 'linked_execution_ids' => [],
            'result' => ['status' => 'secret-status', 'throwable' => ['class' => 'secret class', 'code' => 3]],
        ], 'execution'));

        self::assertSame(['status' => 'cancelled', 'throwable' => null], $sink->published()[0]->normalize()['result']);
    }

    public function testLowAttributeLimitsReportAndDropIncompleteThrowableStructures(): void
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
            $classifier = new DefaultLifecycleClassifier($sink, $health, new SecurityLimits(maxAttributeCount: $limit));
            $throwable = ['class' => 'RuntimeException', 'code' => 42, 'message' => 'secret',
                'previous' => ['class' => 'InvalidArgumentException', 'code' => 7]];
            $classifier->publish(new VerifiedArtifactProjection([
                'id' => 'export-execution', 'status' => 'failed', 'started_at' => 1, 'ended_at' => 2,
                'sequence' => 3, 'linked_execution_ids' => [], 'result' => ['throwable' => $throwable],
            ], 'execution'));
            $classifier->publish(new VerifiedArtifactProjection([
                'id' => 'export-activity', 'execution_id' => 'export-execution', 'status' => 'failed',
                'started_at' => 1, 'ended_at' => 2, 'sequence' => 4, 'throwable' => $throwable,
            ], 'activity'));

            self::assertCount(0, $sink->published(), 'limit ' . $limit);
            self::assertCount(2, $reports, 'limit ' . $limit);
            self::assertStringNotContainsString('secret', json_encode($reports, JSON_THROW_ON_ERROR));
        }
    }
}
