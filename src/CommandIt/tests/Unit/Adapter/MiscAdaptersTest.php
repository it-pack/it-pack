<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\CommandIt\Adapter\Context\AmbientContextStorage;
use ItPack\CommandIt\Adapter\Fiber\FiberRuntimeDetector;
use ItPack\CommandIt\Adapter\Memory\InMemoryCommandExecutionStorage;
use ItPack\CommandIt\Adapter\Null\NullCommandExecutionStorage;
use ItPack\CommandIt\Contract\Domain\Classification;
use ItPack\CommandIt\Contract\Exception\InvalidContextTokenException;
use ItPack\CommandIt\Domain\CommandContext;
use ItPack\CommandIt\Domain\CommandDefinition;
use ItPack\CommandIt\Domain\CommandExecution;
use ItPack\CommandIt\Domain\ValueObject\CommandExecutionId;
use ItPack\CommandIt\Domain\ValueObject\CommandName;

/**
 * @covers \ItPack\CommandIt\Adapter\Fiber\FiberRuntimeDetector
 * @covers \ItPack\CommandIt\Adapter\Null\NullCommandExecutionStorage
 * @covers \ItPack\CommandIt\Adapter\Memory\InMemoryCommandExecutionStorage
 * @covers \ItPack\CommandIt\Adapter\Context\AmbientContextStorage
 */
final class MiscAdaptersTest extends Unit
{
    public function testFiberRuntimeDetectorSync(): void
    {
        $detector = new FiberRuntimeDetector();

        self::assertNull($detector->currentFiber());
        self::assertFalse($detector->isCoroutineRuntime());
    }

    public function testNullStorageFindsNothing(): void
    {
        $storage = new NullCommandExecutionStorage();

        self::assertNull($storage->find(CommandExecutionId::fromString('missing')));
        $storage->store(CommandExecutionId::fromString('x'), $this->execution());
        self::assertNull($storage->find(CommandExecutionId::fromString('x')));
    }

    public function testInMemoryStorageRoundTrips(): void
    {
        $storage = new InMemoryCommandExecutionStorage();
        $id = CommandExecutionId::fromString('exec-1');
        $execution = $this->execution();

        $storage->store($id, $execution);

        self::assertSame($execution, $storage->find($id));
    }

    public function testAmbientContextEntersAndLeaves(): void
    {
        $storage = new AmbientContextStorage();
        $context = new CommandContext()->with('k', 'v', Classification::Internal);

        $token = $storage->enter($context);
        $current = $storage->current();
        self::assertNotNull($current);
        self::assertSame('v', $current->get('k'));

        $storage->leave($token);
        self::assertNull($storage->current());
    }

    public function testAmbientContextRejectsMismatchedToken(): void
    {
        $storage = new AmbientContextStorage();
        $outer = $storage->enter(new CommandContext()->with('k', 'o', Classification::Internal));
        $inner = $storage->enter(new CommandContext()->with('k', 'i', Classification::Internal));

        $this->expectException(InvalidContextTokenException::class);
        $storage->leave($outer);
    }

    private function execution(): CommandExecution
    {
        return CommandExecution::define(
            new CommandDefinition(CommandName::fromString('demo:run')),
            CommandExecutionId::fromString('exec-1'),
            1000,
        );
    }
}
