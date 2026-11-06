<?php

declare(strict_types=1);

namespace Tests\Unit\SDK\Decorator;

use ItPack\RunIt\Contract\Application\ExecutionRunnerInterface;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\Decorator\ExecutedCallableFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExecutedCallableFactory::class)]
final class ExecutedCallableFactoryTest extends TestCase
{
    public function testBuildReturnsWrapperThatInvokesRunnerWithDefinition(): void
    {
        $captured = null;
        $result = 'runner-result';

        $runner = $this->createMock(ExecutionRunnerInterface::class);
        $runner->method('run')->willReturnCallback(
            static function (ExecutionDefinitionInterface $definition, callable $operation) use (&$captured, $result): mixed {
                $captured = $definition;

                return $result;
            }
        );

        $factory = new ExecutedCallableFactory($runner);

        $definition = ExecutionDefinition::create(
            ExecutionName::fromString('factory.op'),
            ExecutionKind::fromString('operation')
        );

        $wrapper = $factory->build(
            static fn (): int => 7,
            $definition
        );

        self::assertSame($result, $wrapper());
        self::assertSame($definition, $captured);
    }
}
