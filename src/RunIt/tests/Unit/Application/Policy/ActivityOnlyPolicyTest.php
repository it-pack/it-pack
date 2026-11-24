<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Policy;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Policy\ActivityOnlyPolicy;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Application\NestedExecutionMode;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ActivityOnlyPolicy::class)]
final class ActivityOnlyPolicyTest extends Unit
{
    public function testResolveWithoutCurrentExecutionReturnsChildExecution(): void
    {
        $policy = new ActivityOnlyPolicy();
        $definition = $this->createMock(ExecutionDefinitionInterface::class);

        $mode = $policy->resolve(null, $definition);

        self::assertSame(NestedExecutionMode::ChildExecution, $mode);
    }

    public function testResolveWithCurrentExecutionReturnsActivityOnly(): void
    {
        $policy = new ActivityOnlyPolicy();
        $current = $this->createMock(ExecutionScopeInterface::class);
        $definition = $this->createMock(ExecutionDefinitionInterface::class);

        $mode = $policy->resolve($current, $definition);

        self::assertSame(NestedExecutionMode::ActivityOnly, $mode);
    }
}
