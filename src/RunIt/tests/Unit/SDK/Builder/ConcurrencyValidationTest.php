<?php

declare(strict_types=1);

namespace Tests\Unit\SDK\Builder;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Fiber\FiberContextStorage;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Propagation\StackContextStorage;
use ItPack\RunIt\Contract\Application\EngineCapability;
use ItPack\RunIt\Contract\Application\EngineCapabilityInterface;
use ItPack\RunIt\Contract\Application\ExecutionEngineInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Domain\Exception\IncompatibleContextStorageException;
use ItPack\RunIt\SDK\Builder\RunItBuilder;
use ItPack\RunIt\SDK\RunItInterface;

/**
 * Concurrency validation at builder assembly time.
 *
 * A shared `StackContextStorage` must not be paired with an engine that
 * declares concurrent execution. Isolated runtime storage (for example
 * `FiberContextStorage`) is accepted. An opt-in validator hook may further
 * constrain or accept pairings.
 */
final class ConcurrencyValidationTest extends Unit
{
    private function builder(): RunItBuilder
    {
        return RunItBuilder::create()
            ->withPublisher(new InMemoryArtifactPublisher())
            ->withClock(new SystemMonotonicClock())
            ->withSequenceAllocator(new InMemorySequenceAllocator())
            ->withSnapshotSerializer(new JsonSnapshotSerializer())
            ->withIdentifierGenerator(new RandomIdentifierGenerator())
        ;
    }

    public function testNonConcurrentEngineWithDefaultStackStorageIsAccepted(): void
    {
        $runtime = $this->builder()->build();

        self::assertInstanceOf(RunItInterface::class, $runtime);
    }

    public function testConcurrentEngineWithSharedStackStorageIsRejected(): void
    {
        $engine = new class () implements ExecutionEngineInterface {
            public function run(ExecutionScopeInterface $scope, callable $operation): mixed
            {
                return $operation($scope);
            }

            public function capability(): EngineCapabilityInterface
            {
                return new EngineCapability(supportsFibers: false, supportsCoroutines: false, concurrent: true);
            }
        };

        $this->expectException(IncompatibleContextStorageException::class);

        $this->builder()
            ->withExecutionEngine($engine)
            ->build()
        ;
    }

    public function testConcurrentEngineWithIsolatedFiberStorageIsAccepted(): void
    {
        $engine = new class () implements ExecutionEngineInterface {
            public function run(ExecutionScopeInterface $scope, callable $operation): mixed
            {
                return $operation($scope);
            }

            public function capability(): EngineCapabilityInterface
            {
                return new EngineCapability(supportsFibers: true, supportsCoroutines: false, concurrent: true);
            }
        };

        $runtime = $this->builder()
            ->withExecutionEngine($engine)
            ->withContextStorage(new FiberContextStorage())
            ->build()
        ;

        self::assertInstanceOf(RunItInterface::class, $runtime);
    }

    public function testConcurrentEngineWithDefaultStackAndRejectingValidatorIsRejected(): void
    {
        $engine = new class () implements ExecutionEngineInterface {
            public function run(ExecutionScopeInterface $scope, callable $operation): mixed
            {
                return $operation($scope);
            }

            public function capability(): EngineCapabilityInterface
            {
                return new EngineCapability(supportsFibers: false, supportsCoroutines: false, concurrent: true);
            }
        };

        $this->expectException(IncompatibleContextStorageException::class);

        $this->builder()
            ->withExecutionEngine($engine)
            ->withConcurrencyValidator(
                static function (EngineCapabilityInterface $capability, ContextStorageInterface $storage): void {
                    if ($capability instanceof EngineCapability
                        && $capability->concurrent
                        && $storage instanceof StackContextStorage
                    ) {
                        throw IncompatibleContextStorageException::sharedStackWithConcurrentEngine();
                    }
                }
            )
            ->build()
        ;
    }

    public function testConcurrentEngineWithIsolatedStorageAndAcceptingValidatorIsAccepted(): void
    {
        $engine = new class () implements ExecutionEngineInterface {
            public function run(ExecutionScopeInterface $scope, callable $operation): mixed
            {
                return $operation($scope);
            }

            public function capability(): EngineCapabilityInterface
            {
                return new EngineCapability(supportsFibers: true, supportsCoroutines: false, concurrent: true);
            }
        };

        $validatorCalled = false;

        $runtime = $this->builder()
            ->withExecutionEngine($engine)
            ->withContextStorage(new FiberContextStorage())
            ->withConcurrencyValidator(
                function (EngineCapabilityInterface $capability, ContextStorageInterface $storage) use (
                    &$validatorCalled
                ): void {
                    $validatorCalled = true;
                }
            )
            ->build()
        ;

        self::assertInstanceOf(RunItInterface::class, $runtime);
        self::assertTrue($validatorCalled, 'the opt-in validator must be invoked at assembly time');
    }

    public function testSynchronousEngineWithCustomValidatorReceivingCapabilityAndStorage(): void
    {
        $capturedCapability = null;
        $capturedStorage = null;

        $runtime = $this->builder()
            ->withConcurrencyValidator(
                static function (EngineCapabilityInterface $capability, ContextStorageInterface $storage) use (
                    &$capturedCapability,
                    &$capturedStorage
                ): void {
                    $capturedCapability = $capability;
                    $capturedStorage = $storage;
                }
            )
            ->build()
        ;

        self::assertInstanceOf(RunItInterface::class, $runtime);
        self::assertInstanceOf(EngineCapability::class, $capturedCapability);
        self::assertFalse($capturedCapability->concurrent);
        self::assertInstanceOf(ContextStorageInterface::class, $capturedStorage);
    }

    public function testExceptionMessageIdentifiesPairingAndDirectsToIsolatedStorage(): void
    {
        $engine = new class () implements ExecutionEngineInterface {
            public function run(ExecutionScopeInterface $scope, callable $operation): mixed
            {
                return $operation($scope);
            }

            public function capability(): EngineCapabilityInterface
            {
                return new EngineCapability(supportsFibers: false, supportsCoroutines: false, concurrent: true);
            }
        };

        try {
            $this->builder()->withExecutionEngine($engine)->build();

            self::fail('expected IncompatibleContextStorageException');
        } catch (IncompatibleContextStorageException $incompatibleContextStorageException) {
            $message = $incompatibleContextStorageException->getMessage();

            self::assertStringContainsString('StackContextStorage', $message);
            self::assertStringContainsString('concurrent', $message);
            self::assertStringContainsString('withContextStorage', $message);
        }
    }
}
