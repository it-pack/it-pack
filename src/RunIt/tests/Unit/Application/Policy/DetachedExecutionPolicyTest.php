<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Policy;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Policy\DetachedExecutionPolicy;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Application\NestedExecutionMode;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DetachedExecutionPolicy::class)]
final class DetachedExecutionPolicyTest extends Unit
{
    public function testResolveWithoutCurrentExecutionReturnsChildExecution(): void
    {
        $policy = new DetachedExecutionPolicy();
        $definition = $this->createMock(ExecutionDefinitionInterface::class);

        $mode = $policy->resolve(null, $definition);

        self::assertSame(NestedExecutionMode::ChildExecution, $mode);
    }

    public function testResolveWithCurrentExecutionReturnsDetachedExecution(): void
    {
        $policy = new DetachedExecutionPolicy();
        $current = $this->createMock(ExecutionScopeInterface::class);
        $definition = $this->createMock(ExecutionDefinitionInterface::class);

        $mode = $policy->resolve($current, $definition);

        self::assertSame(NestedExecutionMode::DetachedExecution, $mode);
    }
}
