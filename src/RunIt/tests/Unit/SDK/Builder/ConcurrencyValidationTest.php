<?php

declare(strict_types=1);

namespace Tests\Unit\SDK\Builder;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Fiber\FiberContextStorage;
use ItPack\RunIt\Application\Propagation\StackActivityContextStorage;
use ItPack\RunIt\Contract\Application\EngineCapability;
use ItPack\RunIt\Contract\Application\EngineCapabilityInterface;
use ItPack\RunIt\Contract\Application\ExecutionEngineInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Domain\Exception\IncompatibleContextStorageException;
use ItPack\RunIt\SDK\Builder\ExtendedRunItBuilderInterface;
use ItPack\RunIt\SDK\Builder\RunItBuilder;
use ItPack\RunIt\SDK\Builder\RunItBuilderInterface;
use ItPack\RunIt\SDK\RunItInterface;
use ReflectionClass;
use RuntimeException;
use Tests\Support\Fixture\RunItFactories;

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
    private function engine(
        bool $supportsFibers = false,
    ): ExecutionEngineInterface {
        return new readonly class (true, $supportsFibers, false) implements ExecutionEngineInterface {
            public function __construct(
                private bool $concurrent,
                private bool $supportsFibers,
                private bool $supportsCoroutines,
            ) {
            }

            public function run(ExecutionScopeInterface $scope, callable $operation): mixed
            {
                return $operation($scope);
            }

            public function capability(): EngineCapabilityInterface
            {
                return new EngineCapability(
                    supportsFibers: $this->supportsFibers,
                    supportsCoroutines: $this->supportsCoroutines,
                    concurrent: $this->concurrent,
                );
            }
        };
    }

    public function testNonConcurrentEngineWithDefaultStackStorageIsAccepted(): void
    {
        $runtime = RunItFactories::configuredBuilder()->build();

        self::assertInstanceOf(RunItInterface::class, $runtime);
    }

    public function testConcurrentEngineWithSharedStackStorageIsRejected(): void
    {
        $engine = $this->engine();

        $this->expectException(IncompatibleContextStorageException::class);

        RunItFactories::configuredBuilder()
            ->withExecutionEngine($engine)
            ->build()
        ;
    }

    public function testConcurrentEngineWithIsolatedFiberStorageIsAccepted(): void
    {
        $engine = $this->engine(supportsFibers: true);

        $runtime = RunItFactories::configuredBuilder()
            ->withExecutionEngine($engine)
            ->withContextStorage(new FiberContextStorage())
            ->build()
        ;

        self::assertInstanceOf(RunItInterface::class, $runtime);
    }

    public function testConcurrentEngineRejectsSharedActivityStorage(): void
    {
        $engine = $this->engine(supportsFibers: true);

        $this->expectException(IncompatibleContextStorageException::class);
        RunItFactories::configuredBuilder()
            ->withExecutionEngine($engine)
            ->withContextStorage(new FiberContextStorage())
            ->withActivityContextStorage(new StackActivityContextStorage())
            ->build()
        ;
    }

    public function testConcurrentEngineWithIsolatedStorageAndRejectingValidatorIsRejected(): void
    {
        $engine = $this->engine();

        $distinctMessage = 'custom validator rejected the pairing';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($distinctMessage);

        RunItFactories::configuredBuilder()
            ->withExecutionEngine($engine)
            ->withContextStorage(new FiberContextStorage())
            ->withConcurrencyValidator(
                static function (EngineCapabilityInterface $capability, ContextStorageInterface $storage): void {
                    if ($capability instanceof EngineCapability && $capability->concurrent) {
                        throw new RuntimeException('custom validator rejected the pairing');
                    }
                }
            )
            ->build()
        ;
    }

    public function testConcurrentEngineWithSharedStackStorageAndAcceptingValidatorIsRejected(): void
    {
        $engine = $this->engine();

        $this->expectException(IncompatibleContextStorageException::class);

        RunItFactories::configuredBuilder()
            ->withExecutionEngine($engine)
            ->withConcurrencyValidator(
                static function (EngineCapabilityInterface $capability, ContextStorageInterface $storage): void {
                    // The built-in shared-stack/concurrent rejection runs first
                    // and cannot be overridden; this accepting validator never runs.
                }
            )
            ->build()
        ;
    }

    public function testConcurrentEngineWithIsolatedStorageAndAcceptingValidatorIsAccepted(): void
    {
        $engine = $this->engine(supportsFibers: true);

        $validatorCalled = false;

        $runtime = RunItFactories::configuredBuilder()
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

        RunItFactories::configuredBuilder()
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

        self::assertInstanceOf(EngineCapability::class, $capturedCapability);
        self::assertFalse($capturedCapability->concurrent);
        self::assertInstanceOf(ContextStorageInterface::class, $capturedStorage);
    }

    public function testExceptionMessageIdentifiesPairingAndDirectsToIsolatedStorage(): void
    {
        $engine = $this->engine();

        try {
            RunItFactories::configuredBuilder()->withExecutionEngine($engine)->build();

            self::fail('expected IncompatibleContextStorageException');
        } catch (IncompatibleContextStorageException $incompatibleContextStorageException) {
            $message = $incompatibleContextStorageException->getMessage();

            self::assertStringContainsString('StackContextStorage', $message);
            self::assertStringContainsString('concurrent', $message);
            self::assertStringContainsString('withContextStorage', $message);
        }
    }

    public function testExtendedRunItBuilderInterfaceExtendsRunItBuilderInterface(): void
    {
        $reflection = new ReflectionClass(ExtendedRunItBuilderInterface::class);

        self::assertTrue($reflection->isSubclassOf(RunItBuilderInterface::class));
    }

    public function testRunItBuilderImplementsExtendedRunItBuilderInterface(): void
    {
        $builder = RunItBuilder::create();

        self::assertInstanceOf(ExtendedRunItBuilderInterface::class, $builder);
    }

    public function testRunItBuilderInterfaceDoesNotDeclareConcurrencyValidator(): void
    {
        $reflection = new ReflectionClass(RunItBuilderInterface::class);

        self::assertFalse($reflection->hasMethod('withConcurrencyValidator'));

        $extendedReflection = new ReflectionClass(ExtendedRunItBuilderInterface::class);

        self::assertTrue($extendedReflection->hasMethod('withConcurrencyValidator'));
    }
}
