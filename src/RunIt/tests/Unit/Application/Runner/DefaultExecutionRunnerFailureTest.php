<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Runner;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Runner\DefaultExecutionRunner;
use ItPack\RunIt\Contract\Application\ExecutionCompletionPolicyInterface;
use ItPack\RunIt\Contract\Application\ExecutionFactoryInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Application\ExecutionSnapshotFactoryInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ThrowableMapperInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Tests\Support\Fixture\RunItTestKit;

#[CoversClass(DefaultExecutionRunner::class)]
final class DefaultExecutionRunnerFailureTest extends Unit
{
    public function testStartPublicationFailurePreservesResultAndCleansUp(): void
    {
        $this->exerciseFailure('start');
    }

    public function testTerminalPublicationFailurePreservesResultAndCleansUp(): void
    {
        $this->exerciseFailure('terminal');
    }

    public function testDetachFailurePreservesResult(): void
    {
        $this->exerciseFailure('detach');
    }

    public function testBusinessExceptionSurvivesTerminalPublicationAndDetachFailures(): void
    {
        $this->exerciseFailure('business');
    }

    private function exerciseFailure(string $scenario): void
    {
        $kit = new RunItTestKit();
        $definition = ExecutionDefinition::create(
            ExecutionName::fromString('op'),
            ExecutionKind::fromString('task'),
        );
        $failure = new RuntimeException('instrumentation failure');
        $businessFailure = new RuntimeException('business failure');
        $published = 0;
        $publisher = $this->createMock(ArtifactPublisherInterface::class);
        $publisher->method('publish')->willReturnCallback(
            static function (ExecutionArtifactInterface $artifact) use (&$published, $scenario, $failure): void {
                ++$published;
                if (($scenario === 'start' && $published === 1)
                    || (in_array($scenario, ['terminal', 'business'], true) && $published === 2)) {
                    throw $failure;
                }
            },
        );
        $kit = new RunItTestKit(publisher: $publisher);

        $caught = null;
        $result = null;
        try {
            $result = $kit->runner->run($definition, static function (ExecutionScopeInterface $scope) use (
                $scenario,
                $businessFailure,
            ): string {
                if ($scenario === 'business') {
                    throw $businessFailure;
                }
                return 'business result';
            });
        } catch (RuntimeException $runtimeException) {
            $caught = $runtimeException;
        }

        self::assertSame($scenario === 'business' ? $businessFailure : null, $caught);
        self::assertSame(
            $scenario === 'business' ? null : 'business result',
            $result,
        );
    }

    public function testCompletionPolicyFailureIsReportedAndResultPreserved(): void
    {
        $completionPolicy = $this->createMock(ExecutionCompletionPolicyInterface::class);
        $completionPolicy->method('resolveOpenActivities')->willThrowException(
            new RuntimeException('completion policy failure'),
        );
        $kit = new RunItTestKit(completionPolicy: $completionPolicy);
        $definition = ExecutionDefinition::create(
            ExecutionName::fromString('op'),
            ExecutionKind::fromString('task'),
        );

        $result = $kit->runner->run($definition, static fn (): string => 'ok');

        self::assertSame('ok', $result);
    }

    public function testThrowableMapperFailureUsesFallbackDescription(): void
    {
        $throwableMapper = $this->createMock(ThrowableMapperInterface::class);
        $throwableMapper->method('describe')->willThrowException(new RuntimeException('mapper failure'));
        $kit = new RunItTestKit(throwableMapper: $throwableMapper);
        $definition = ExecutionDefinition::create(
            ExecutionName::fromString('op'),
            ExecutionKind::fromString('task'),
        );

        $caught = null;
        try {
            $kit->runner->run($definition, static function (): never {
                throw new RuntimeException('business failure');
            });
        } catch (RuntimeException $runtimeException) {
            $caught = $runtimeException;
        }

        self::assertSame('business failure', $caught->getMessage());
    }

    public function testSnapshotFactoryFailureIsReportedAndResultPreserved(): void
    {
        $snapshotFactory = $this->createMock(ExecutionSnapshotFactoryInterface::class);
        $snapshotFactory->method('terminal')->willThrowException(new RuntimeException('snapshot failure'));
        $kit = new RunItTestKit(snapshotFactory: $snapshotFactory);
        $definition = ExecutionDefinition::create(
            ExecutionName::fromString('op'),
            ExecutionKind::fromString('task'),
        );

        $result = $kit->runner->run($definition, static fn (): string => 'ok');

        self::assertSame('ok', $result);
    }

    public function testContextStorageDetachFailureIsReportedAndResultPreserved(): void
    {
        $storage = $this->createMock(ContextStorageInterface::class);
        $token = $this->createMock(ContextTokenInterface::class);
        $storage->method('attach')->willReturn($token);
        $storage->method('detach')->willThrowException(new RuntimeException('detach failure'));

        $kit = new RunItTestKit(storage: $storage);
        $definition = ExecutionDefinition::create(
            ExecutionName::fromString('op'),
            ExecutionKind::fromString('task'),
        );

        $result = $kit->runner->run($definition, static fn (): string => 'ok');

        self::assertSame('ok', $result);
    }

    public function testExecutionFactoryFailureIsReportedAndRethrown(): void
    {
        $factory = $this->createMock(ExecutionFactoryInterface::class);
        $factory->method('create')->willThrowException(new RuntimeException('factory failure'));
        $kit = new RunItTestKit(executionFactory: $factory);
        $definition = ExecutionDefinition::create(
            ExecutionName::fromString('op'),
            ExecutionKind::fromString('task'),
        );

        $caught = null;
        try {
            $kit->runner->run($definition, static fn (): string => 'ok');
        } catch (RuntimeException $runtimeException) {
            $caught = $runtimeException;
        }

        self::assertInstanceOf(RuntimeException::class, $caught);
    }
}
