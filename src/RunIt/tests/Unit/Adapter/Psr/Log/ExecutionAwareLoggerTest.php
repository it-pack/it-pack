<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Psr\Log;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Psr\Log\ExecutionAwareLogger;
use ItPack\RunIt\Adapter\Psr\Log\LoggerCollisionPolicy;
use ItPack\RunIt\Contract\Application\ActivityScopeInterface;
use ItPack\RunIt\Domain\Activity\ActivityDefinition;
use ItPack\RunIt\Domain\Activity\ActivityKind;
use ItPack\RunIt\Domain\Activity\ActivityName;
use ItPack\RunIt\Domain\Context\ExecutionContext;
use ItPack\RunIt\Domain\Execution\Execution;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Shared\Instant;
use Tests\Support\Fixture\Psr\FakeCurrentExecution;
use Tests\Support\Fixture\Psr\FakeExecutionScope;
use Tests\Support\Fixture\Psr\InMemoryLogger;
use Tests\Support\Fixture\Psr\RecordingObservationRecorder;

final class ExecutionAwareLoggerTest extends Unit
{
    private function scopeWithExecution(string $id = 'exec-1'): FakeExecutionScope
    {
        $execution = Execution::start(
            ExecutionId::fromString($id),
            ExecutionDefinition::create(ExecutionName::fromString('http.request'), ExecutionKind::fromString('http')),
            Instant::now(),
        );

        return new FakeExecutionScope(
            $execution,
            ExecutionContext::empty(),
            new RecordingObservationRecorder()
        );
    }

    public function testEnrichesLogWithExecutionId(): void
    {
        $logger = new InMemoryLogger();
        $decorator = new ExecutionAwareLogger($logger, new FakeCurrentExecution($this->scopeWithExecution('exec-7')));

        $decorator->info('hello', ['user' => 'ada']);

        $entry = $logger->last();
        self::assertNotNull($entry);
        self::assertSame('hello', $entry['message']);
        self::assertSame('exec-7', $entry['context']['execution_id']);
        self::assertSame('ada', $entry['context']['user']);
    }

    public function testEnrichesLogWithActivityIdWhenActive(): void
    {
        $parent = $this->scopeWithExecution('exec-7');
        $activityScope = null;
        $parent->activities()->run(
            ActivityDefinition::create(ActivityName::fromString('db.query'), ActivityKind::fromString('db')),
            static function (ActivityScopeInterface $scope) use (&$activityScope): void {
                $activityScope = $scope;
            },
        );

        $logger = new InMemoryLogger();
        $decorator = new ExecutionAwareLogger($logger, new FakeCurrentExecution($activityScope));

        $decorator->warning('query slow');

        $entry = $logger->last();
        self::assertNotNull($entry);
        self::assertSame('exec-7', $entry['context']['execution_id']);
        self::assertNotNull($entry['context']['activity_id']);
        self::assertNotSame('', $entry['context']['activity_id']);
    }

    public function testPassesThroughWhenNoExecutionIsActive(): void
    {
        $logger = new InMemoryLogger();
        $decorator = new ExecutionAwareLogger($logger, new FakeCurrentExecution(null));

        $decorator->info('standalone', ['k' => 'v']);

        $entry = $logger->last();
        self::assertNotNull($entry);
        self::assertSame('standalone', $entry['message']);
        self::assertArrayNotHasKey('execution_id', $entry['context']);
        self::assertSame('v', $entry['context']['k']);
    }

    public function testPreserveCallerCollisionPolicyKeepsCallersKey(): void
    {
        $logger = new InMemoryLogger();
        $decorator = new ExecutionAwareLogger(
            $logger,
            new FakeCurrentExecution($this->scopeWithExecution('exec-7')),
            LoggerCollisionPolicy::PreserveCaller,
        );

        $decorator->info('msg', ['execution_id' => 'caller-provided']);

        $entry = $logger->last();
        self::assertNotNull($entry);
        self::assertSame('caller-provided', $entry['context']['execution_id']);
        self::assertSame('exec-7', $entry['context']['runit.execution_id']);
    }

    public function testOverwriteCollisionPolicyReplacesCallersKey(): void
    {
        $logger = new InMemoryLogger();
        $decorator = new ExecutionAwareLogger(
            $logger,
            new FakeCurrentExecution($this->scopeWithExecution('exec-7')),
            LoggerCollisionPolicy::Overwrite,
        );

        $decorator->info('msg', ['execution_id' => 'caller-provided']);

        $entry = $logger->last();
        self::assertNotNull($entry);
        self::assertSame('exec-7', $entry['context']['execution_id']);
        self::assertArrayNotHasKey('runit.execution_id', $entry['context']);
    }
}
