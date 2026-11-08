<?php

declare(strict_types=1);

namespace Tests\Unit\SDK\Decorator;

use ItPack\RunIt\Application\Propagation\CurrentExecution;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\Decorator\ExecutedCallable;
use ItPack\RunIt\SDK\Exception\MissingExecutionDefinitionException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Fixture\RunItTestKit;

#[CoversClass(ExecutedCallable::class)]
#[CoversClass(MissingExecutionDefinitionException::class)]
final class ExecutedCallableTest extends TestCase
{
    private RunItTestKit $kit;

    protected function setUp(): void
    {
        $this->kit = new RunItTestKit();
    }

    private function definition(): ExecutionDefinition
    {
        return ExecutionDefinition::create(
            ExecutionName::fromString('decorator.spec'),
            ExecutionKind::fromString('operation')
        );
    }

    public function testClosurePreservesResultAndArguments(): void
    {
        $wrapped = new ExecutedCallable(
            $this->kit->runner,
            static fn (string $a, int $b): string => $a . ':' . $b,
            $this->definition(),
        );

        self::assertSame('x:2', $wrapped('x', 2));
    }

    public function testInvokableObjectIsWrapped(): void
    {
        $wrapped = new ExecutedCallable(
            $this->kit->runner,
            new Concatenator(),
            $this->definition(),
        );

        self::assertSame('ab', $wrapped('a', 'b'));
    }

    public function testCallableArrayIsWrapped(): void
    {
        $wrapped = new ExecutedCallable(
            $this->kit->runner,
            [new Concatenator(), 'join'],
            $this->definition(),
        );

        self::assertSame('cd', $wrapped('c', 'd'));
    }

    public function testOriginalThrowableIsPreserved(): void
    {
        $wrapped = new ExecutedCallable(
            $this->kit->runner,
            static function (): never {
                throw new DecoratorFailure();
            },
            $this->definition(),
        );

        $this->expectException(DecoratorFailure::class);

        $wrapped();
    }

    public function testScopeIsAvailableToOperation(): void
    {
        $kit = $this->kit;
        $captured = null;
        $wrapped = new ExecutedCallable(
            $this->kit->runner,
            static function () use ($kit, &$captured): void {
                $captured = new CurrentExecution($kit->storage)->get();
            },
            $this->definition(),
        );

        $wrapped();

        self::assertInstanceOf(ExecutionScopeInterface::class, $captured);
    }

    public function testDefinitionFactoryReceivesArguments(): void
    {
        $wrapped = new ExecutedCallable(
            $this->kit->runner,
            static fn (string $name): string => 'hello ' . $name,
            definition: null,
            definitionFactory: function (array $args): ExecutionDefinition {
                self::assertSame(['world'], $args);

                return ExecutionDefinition::create(
                    ExecutionName::fromString('factory.op'),
                    ExecutionKind::fromString('operation')
                );
            },
        );

        self::assertSame('hello world', $wrapped('world'));
    }

    public function testMissingDefinitionThrowsOnInvoke(): void
    {
        $wrapped = new ExecutedCallable($this->kit->runner, static fn (): int => 1, definition: null);

        $this->expectException(MissingExecutionDefinitionException::class);

        $wrapped();
    }
}

final class Concatenator
{
    public function __invoke(string $a, string $b): string
    {
        return $a . $b;
    }

    public function join(string $a, string $b): string
    {
        return $a . $b;
    }
}

final class DecoratorFailure extends RuntimeException
{
}
