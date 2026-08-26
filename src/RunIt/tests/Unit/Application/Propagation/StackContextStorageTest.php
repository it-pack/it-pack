<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Propagation;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Propagation\StackContextStorage;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Domain\Context\ExecutionContext;
use ItPack\RunIt\Domain\Exception\InvalidContextTokenException;
use ItPack\RunIt\Domain\Execution\Execution;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Shared\Instant;
use Tests\Support\Fixture\RunItTestKit;

final class StackContextStorageTest extends Unit
{
    private function scope(RunItTestKit $kit, string $id): ExecutionScopeInterface
    {
        return $kit->buildScope(
            Execution::start(
                ExecutionId::fromString($id),
                ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
                Instant::of(1)
            ),
            ExecutionContext::empty(),
        );
    }

    public function testCurrentIsNullWhenEmpty(): void
    {
        $storage = new StackContextStorage();
        self::assertNull($storage->current());
    }

    public function testAttachSetsCurrentAndDetachRestores(): void
    {
        $kit = new RunItTestKit();
        $storage = new StackContextStorage();
        $scope = $this->scope($kit, 'exec-1');

        $token = $storage->attach($scope);
        self::assertSame($scope, $storage->current());

        $storage->detach($token);
        self::assertNull($storage->current());
    }

    public function testNestedAttachFollowsLastInFirstOut(): void
    {
        $kit = new RunItTestKit();
        $storage = new StackContextStorage();
        $outer = $this->scope($kit, 'exec-1');
        $inner = $this->scope($kit, 'exec-2');

        $outerToken = $storage->attach($outer);
        $innerToken = $storage->attach($inner);
        self::assertSame($inner, $storage->current());

        $storage->detach($innerToken);
        self::assertSame($outer, $storage->current());

        $storage->detach($outerToken);
        self::assertNull($storage->current());
    }

    public function testDetachingInWrongOrderIsRejected(): void
    {
        $kit = new RunItTestKit();
        $storage = new StackContextStorage();
        $outer = $this->scope($kit, 'exec-1');
        $inner = $this->scope($kit, 'exec-2');

        $outerToken = $storage->attach($outer);
        $storage->attach($inner);

        $this->expectException(InvalidContextTokenException::class);
        $storage->detach($outerToken);
    }

    public function testDoubleDetachIsRejected(): void
    {
        $kit = new RunItTestKit();
        $storage = new StackContextStorage();
        $token = $storage->attach($this->scope($kit, 'exec-1'));

        $storage->detach($token);

        $this->expectException(InvalidContextTokenException::class);
        $storage->detach($token);
    }

    public function testForeignTokenIsRejected(): void
    {
        $kit = new RunItTestKit();
        $storage = new StackContextStorage();
        $other = new StackContextStorage();
        $token = $other->attach($this->scope($kit, 'exec-1'));

        $this->expectException(InvalidContextTokenException::class);
        $storage->detach($token);
    }
}
