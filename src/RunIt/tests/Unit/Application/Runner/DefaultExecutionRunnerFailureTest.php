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
use ItPack\RunIt\Contract\Exception\InvalidValueExceptionInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Tests\Support\Fixture\RunItTestKit;
use Throwable;

#[CoversClass(DefaultExecutionRunner::class)]
final class DefaultExecutionRunnerFailureTest extends Unit
{
    /**
     * @throws InvalidValueExceptionInterface
     * @throws Throwable
     */
    public function testStartPublicationFailurePreservesResultAndCleansUp(): void
    {
        $this->exerciseFailure('start');
    }

    /**
     * @throws InvalidValueExceptionInterface
     * @throws Throwable
     */
    public function testTerminalPublicationFailurePreservesResultAndCleansUp(): void
    {
        $this->exerciseFailure('terminal');
    }

    /**
     * @throws InvalidValueExceptionInterface
     * @throws Throwable
     */
    public function testDetachFailurePreservesResult(): void
    {
        $this->exerciseFailure('detach');
    }

    /**
     * @throws InvalidValueExceptionInterface
     * @throws Throwable
     */
    public function testBusinessExceptionSurvivesTerminalPublicationAndDetachFailures(): void
    {
        $this->exerciseFailure('business');
    }

    /**
     * @throws InvalidValueExceptionInterface
     * @throws Throwable
     */
    public function testCompletionPolicyFailureIsReportedAndResultPreserved(): void
    {
        $completionPolicy = $this->createMock(ExecutionCompletionPolicyInterface::class);
        $completionPolicy->method('resolveOpenActivities')->willThrowException(
            new RuntimeException('completion policy failure'),
        );
        $reported = 0;
        $kit = new RunItTestKit(
            onPublicationFailure: static function (Throwable $failure) use (&$reported): void {
                ++$reported;
            },
            completionPolicy: $completionPolicy,
        );
        $definition = ExecutionDefinition::create(
            ExecutionName::fromString('op'),
            ExecutionKind::fromString('task'),
        );

        $result = $kit->runner->run($definition, static fn (): string => 'ok');

        self::assertSame('ok', $result);
        self::assertSame(1, $reported);
    }

    /**
     * @throws InvalidValueExceptionInterface
     * @throws Throwable
     */
    public function testThrowableMapperFailureUsesFallbackDescription(): void
    {
        $throwableMapper = $this->createMock(ThrowableMapperInterface::class);
        $throwableMapper->method('describe')->willThrowException(new RuntimeException('mapper failure'));
        $reported = 0;
        $kit = new RunItTestKit(
            onPublicationFailure: static function (Throwable $failure) use (&$reported): void {
                ++$reported;
            },
            throwableMapper: $throwableMapper,
        );
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
        self::assertSame(1, $reported);
    }

    /**
     * @throws InvalidValueExceptionInterface
     * @throws Throwable
     */
    public function testSnapshotFactoryFailureIsReportedAndResultPreserved(): void
    {
        $snapshotFactory = $this->createMock(ExecutionSnapshotFactoryInterface::class);
        $snapshotFactory->method('terminal')->willThrowException(new RuntimeException('snapshot failure'));
        $reported = 0;
        $kit = new RunItTestKit(
            onPublicationFailure: static function (Throwable $failure) use (&$reported): void {
                ++$reported;
            },
            snapshotFactory: $snapshotFactory,
        );
        $definition = ExecutionDefinition::create(
            ExecutionName::fromString('op'),
            ExecutionKind::fromString('task'),
        );

        $result = $kit->runner->run($definition, static fn (): string => 'ok');

        self::assertSame('ok', $result);
        self::assertSame(1, $reported);
    }

    /**
     * @throws InvalidValueExceptionInterface
     * @throws Throwable
     */
    public function testContextStorageDetachFailureIsReportedAndResultPreserved(): void
    {
        $storage = $this->createMock(ContextStorageInterface::class);
        $token = $this->createMock(ContextTokenInterface::class);
        $storage->method('attach')->willReturn($token);
        $storage->method('detach')->willThrowException(new RuntimeException('detach failure'));

        $reported = 0;
        $kit = new RunItTestKit(
            onPublicationFailure: static function (Throwable $failure) use (&$reported): void {
                ++$reported;
            },
            storage: $storage,
        );
        $definition = ExecutionDefinition::create(
            ExecutionName::fromString('op'),
            ExecutionKind::fromString('task'),
        );

        $result = $kit->runner->run($definition, static fn (): string => 'ok');

        self::assertSame('ok', $result);
        self::assertSame(1, $reported);
    }

    /**
     * @throws InvalidValueExceptionInterface
     * @throws Throwable
     */
    public function testExecutionFactoryFailureIsReportedAndRethrown(): void
    {
        $factory = $this->createMock(ExecutionFactoryInterface::class);
        $factory->method('create')->willThrowException(new RuntimeException('factory failure'));
        $reported = 0;
        $kit = new RunItTestKit(
            onPublicationFailure: static function (Throwable $failure) use (&$reported): void {
                ++$reported;
            },
            executionFactory: $factory,
        );
        $definition = $this->createExecutionDefinition();

        $caught = null;
        try {
            $kit->runner->run($definition, static fn (): string => 'ok');
        } catch (RuntimeException $runtimeException) {
            $caught = $runtimeException;
        }

        self::assertInstanceOf(RuntimeException::class, $caught);
        self::assertSame(1, $reported);
    }

    /**
     * @throws InvalidValueExceptionInterface
     * @throws Throwable
     */
    private function exerciseFailure(string $scenario): void
    {
        $definition = $this->createExecutionDefinition();
        $failure = new RuntimeException('instrumentation failure');
        $businessFailure = new RuntimeException('business failure');
        $published = 0;
        $publisher = $this->createArtifactPublisher($published, $scenario, $failure);

        $storage = null;
        if (in_array($scenario, ['detach', 'business'], true)) {
            $token = $this->createMock(ContextTokenInterface::class);
            $storage = $this->createMock(ContextStorageInterface::class);
            $storage->method('attach')->willReturn($token);
            $storage->expects($this->once())->method('detach')
                ->willThrowException(
                    new RuntimeException('detach failure')
                )
            ;
        }

        $reported = 0;
        $reportedFailures = [];
        $kit = new RunItTestKit(
            publisher: $publisher,
            onPublicationFailure: static function (Throwable $failure) use (&$reported, &$reportedFailures): void {
                ++$reported;
                $reportedFailures[] = $failure;
            },
            storage: $storage,
        );

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

        if ($scenario === 'business') {
            self::assertSame(2, $reported, 'terminal publication and detach failures must both be reported');
        } elseif ($scenario === 'detach') {
            self::assertSame(1, $reported, 'detach failure must reach the health callback');
        } else {
            self::assertTrue(in_array($failure, $reportedFailures, true), 'start/terminal publication failure must be reported');
            self::assertNull($kit->storage->current(), 'previous context must be restored after normal detach');
        }
    }

    /**
     * @throws InvalidValueExceptionInterface
     */
    private function createExecutionDefinition(): ExecutionDefinition
    {
        return ExecutionDefinition::create(
            ExecutionName::fromString('op'),
            ExecutionKind::fromString('task'),
        );
    }

    private function createArtifactPublisher(
        int $published,
        string $scenario,
        RuntimeException $failure
    ): ArtifactPublisherInterface {
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
        return $publisher;
    }
}
