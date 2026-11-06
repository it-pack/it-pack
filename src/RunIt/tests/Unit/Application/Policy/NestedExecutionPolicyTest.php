<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Policy;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Policy\ActivityOnlyPolicy;
use ItPack\RunIt\Application\Policy\ChildExecutionPolicy;
use ItPack\RunIt\Application\Policy\DetachedExecutionPolicy;
use ItPack\RunIt\Application\Policy\RejectNestedPolicy;
use ItPack\RunIt\Contract\Application\NestedExecutionMode;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;
use ItPack\RunIt\Domain\Context\ExecutionContext;
use ItPack\RunIt\Domain\Execution\Execution;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Shared\Instant;
use Tests\Support\Fixture\RunItTestKit;

final class NestedExecutionPolicyTest extends Unit
{
    private function definition(): ExecutionDefinitionInterface
    {
        return ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task'));
    }

    private function running(): Execution
    {
        return Execution::start(ExecutionId::fromString('outer'), $this->definition(), Instant::of(1));
    }

    public function testChildExecutionPolicyYieldsRootWhenNoParent(): void
    {
        $policy = new ChildExecutionPolicy();
        self::assertSame(NestedExecutionMode::ChildExecution, $policy->resolve(current: null, definition: $this->definition()));
    }

    public function testChildExecutionPolicyYieldsChildWhenParentActive(): void
    {
        $kit = new RunItTestKit();
        $current = $kit->buildScope($this->running(), ExecutionContext::empty());
        $policy = new ChildExecutionPolicy();
        self::assertSame(NestedExecutionMode::ChildExecution, $policy->resolve($current, $this->definition()));
    }

    public function testActivityOnlyPolicyYieldsActivityWhenParentActive(): void
    {
        $kit = new RunItTestKit();
        $current = $kit->buildScope($this->running(), ExecutionContext::empty());
        $policy = new ActivityOnlyPolicy();
        self::assertSame(NestedExecutionMode::ActivityOnly, $policy->resolve($current, $this->definition()));
    }

    public function testDetachedPolicyYieldsDetachedWhenParentActive(): void
    {
        $kit = new RunItTestKit();
        $current = $kit->buildScope($this->running(), ExecutionContext::empty());
        $policy = new DetachedExecutionPolicy();
        self::assertSame(NestedExecutionMode::DetachedExecution, $policy->resolve($current, $this->definition()));
    }

    public function testRejectPolicyRejectsWhenParentActive(): void
    {
        $kit = new RunItTestKit();
        $current = $kit->buildScope($this->running(), ExecutionContext::empty());
        $policy = new RejectNestedPolicy();
        self::assertSame(NestedExecutionMode::RejectNested, $policy->resolve($current, $this->definition()));
    }
}
