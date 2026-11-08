<?php

declare(strict_types=1);

namespace Tests\Unit\SDK;

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Contract\Application\CurrentExecutionInterface;
use ItPack\RunIt\Contract\Application\ExecutionRunnerInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\Attribute\ExecutedAs;
use ItPack\RunIt\SDK\Builder\RunItBuilder;
use ItPack\RunIt\SDK\Decorator\ExecutedCallableInterface;
use ItPack\RunIt\SDK\DefaultRunIt;
use ItPack\RunIt\SDK\Exception\MissingExecutionDefinitionException;
use ItPack\RunIt\SDK\RunItInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(DefaultRunIt::class)]
#[CoversClass(RunItBuilder::class)]
final class DefaultRunItTest extends TestCase
{
    private function runtime(): RunItInterface
    {
        return RunItBuilder::create()
            ->withPublisher(new InMemoryArtifactPublisher())
            ->withClock(new SystemMonotonicClock())
            ->withSequenceAllocator(new InMemorySequenceAllocator())
            ->withSnapshotSerializer(new JsonSnapshotSerializer())
            ->withIdentifierGenerator(new RandomIdentifierGenerator())
            ->build()
        ;
    }

    private function definition(): ExecutionDefinition
    {
        return ExecutionDefinition::create(
            ExecutionName::fromString('facade.spec'),
            ExecutionKind::fromString('operation')
        );
    }

    public function testBuildReturnsInterfaceBackedRuntime(): void
    {
        self::assertInstanceOf(RunItInterface::class, $this->runtime());
    }

    public function testRunReturnsOperationResult(): void
    {
        $runtime = $this->runtime();

        $result = $runtime->run($this->definition(), static fn (): string => 'ok');

        self::assertSame('ok', $result);
    }

    public function testCurrentExecutionIsActiveOnlyInsideRun(): void
    {
        $runtime = $this->runtime();
        $seenInside = null;

        $runtime->run($this->definition(), static function () use ($runtime, &$seenInside): void {
            $seenInside = $runtime->currentExecution();
        });

        self::assertInstanceOf(ExecutionScopeInterface::class, $seenInside);
        self::assertNull($runtime->currentExecution());
    }

    public function testWrapReturnsExecutedCallable(): void
    {
        $runtime = $this->runtime();
        $wrapped = $runtime->wrap(
            static fn (): int => 7,
            $this->definition(),
        );

        self::assertInstanceOf(ExecutedCallableInterface::class, $wrapped);
        self::assertSame(7, $wrapped());
    }

    public function testWrapDerivesDefinitionFromAttribute(): void
    {
        $runtime = $this->runtime();

        $wrapped = $runtime->wrap(attributeWrappedOperation(...));

        self::assertSame('attribute-result', $wrapped());
    }

    public function testWrapWithoutDefinitionOrAttributeThrowsOnInvoke(): void
    {
        $runtime = $this->runtime();
        $wrapped = $runtime->wrap(static fn (): int => 1);

        $this->expectException(MissingExecutionDefinitionException::class);

        $wrapped();
    }

    public function testWrapWithCallableDefinitionResolvesPerInvocation(): void
    {
        $runtime = $this->runtime();
        $factoryInvoked = false;

        $factory = function () use (&$factoryInvoked): ExecutionDefinitionInterface {
            $factoryInvoked = true;

            return $this->definition();
        };

        $wrapped = $runtime->wrap(
            static fn (): int => 7,
            $factory,
        );

        self::assertSame(7, $wrapped());
        self::assertTrue($factoryInvoked, 'the callable definition factory must be invoked per invocation');
    }

    public function testWrapPassesInvocationArgsToCallableDefinitionFactory(): void
    {
        $receivedArgs = [];

        $runner = $this->createMock(ExecutionRunnerInterface::class);
        $runner->method('run')->willReturn('runner-result');

        $factory = function (array $args) use (&$receivedArgs): ExecutionDefinitionInterface {
            $receivedArgs = $args;

            return ExecutionDefinition::create(
                ExecutionName::fromString('arg.op'),
                ExecutionKind::fromString('operation')
            );
        };

        $runtime = new DefaultRunIt(
            $runner,
            new class () implements CurrentExecutionInterface {
                public function get(): ?ExecutionScopeInterface
                {
                    return null;
                }

                public function require(): ExecutionScopeInterface
                {
                    throw new RuntimeException('no active execution');
                }
            },
        );

        $wrapped = $runtime->wrap(
            static fn (string $name): string => 'result-'.$name,
            $factory,
        );

        self::assertSame('runner-result', $wrapped('hello'));
        self::assertSame(['hello'], $receivedArgs, 'the invocation argument array is passed to the factory');
    }
}

#[ExecutedAs(name: 'attribute.op', kind: 'operation')]
function attributeWrappedOperation(): string
{
    return 'attribute-result';
}
