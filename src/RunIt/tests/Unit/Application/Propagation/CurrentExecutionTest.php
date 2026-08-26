<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Propagation;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Propagation\CurrentExecution;
use ItPack\RunIt\Application\Propagation\StackContextStorage;
use ItPack\RunIt\Domain\Exception\NoActiveExecutionException;
use ItPack\RunIt\Domain\Execution\Execution;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Shared\Instant;
use Tests\Support\Fixture\RunItTestKit;

final class CurrentExecutionTest extends Unit
{
    public function testGetReturnsNullWhenNoExecutionActive(): void
    {
        $current = new CurrentExecution(new StackContextStorage());
        self::assertNull($current->get());
    }

    public function testRequireThrowsWhenNoExecutionActive(): void
    {
        $current = new CurrentExecution(new StackContextStorage());

        $this->expectException(NoActiveExecutionException::class);
        $current->require();
    }

    public function testGetAndRequireReturnAttachedScope(): void
    {
        $kit = new RunItTestKit();
        $storage = new StackContextStorage();
        $current = new CurrentExecution($storage);
        $scope = $kit->buildScope(
            Execution::start(
                ExecutionId::fromString('exec-1'),
                ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
                Instant::of(1)
            ),
            \ItPack\RunIt\Domain\Context\ExecutionContext::empty(),
        );

        $token = $storage->attach($scope);

        self::assertSame($scope, $current->get());
        self::assertSame($scope, $current->require());

        $storage->detach($token);
        self::assertNull($current->get());
    }
}
