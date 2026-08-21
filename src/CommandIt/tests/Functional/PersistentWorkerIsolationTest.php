<?php

declare(strict_types=1);

namespace Tests\Functional;

use Codeception\Test\Unit;
use Fiber;
use ItPack\CommandIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\CommandIt\Adapter\Common\SystemClock;
use ItPack\CommandIt\Adapter\Context\AmbientContextStorage;
use ItPack\CommandIt\Adapter\Context\FiberBoundContextStorage;
use ItPack\CommandIt\Adapter\Memory\InMemoryCommandExecutionStorage;
use ItPack\CommandIt\Adapter\Reporting\CollectingExecutionReporter;
use ItPack\CommandIt\Application\CommandExecutionCoordinator;
use ItPack\CommandIt\Application\CommandExecutor;
use ItPack\CommandIt\Application\CommandRegistry;
use ItPack\CommandIt\Application\HandlerResolver;
use ItPack\CommandIt\Contract\Command\CommandHandlerInterface;
use ItPack\CommandIt\Contract\Command\CommandInterface;
use ItPack\CommandIt\Contract\Command\CommandResultInterface;
use ItPack\CommandIt\Contract\Domain\Classification;
use ItPack\CommandIt\Domain\Command;
use ItPack\CommandIt\Domain\CommandContext;
use ItPack\CommandIt\Domain\CommandDefinition;
use ItPack\CommandIt\Domain\CommandInput;
use ItPack\CommandIt\Domain\CommandResult;
use ItPack\CommandIt\Domain\ValueObject\CommandName;

/**
 * @covers \ItPack\CommandIt\Application\CommandExecutor
 * @covers \ItPack\CommandIt\Adapter\Context\FiberBoundContextStorage
 *
 * Persistent-worker simulation: commands are processed repeatedly (and, in the
 * coroutine case, under distinct fibers) to prove execution context never bleeds
 * across requests — each dispatch restores the prior context in `finally`.
 */
final class PersistentWorkerIsolationTest extends Unit
{
    private CommandRegistry $registry;

    private CommandExecutor $executor;

    private FiberBoundContextStorage $storage;

    private FakeFiberDetector $detector;

    protected function setUp(): void
    {
        $this->registry = new CommandRegistry();
        $this->detector = new FakeFiberDetector();
        $this->storage = new FiberBoundContextStorage($this->detector, new AmbientContextStorage());

        $this->executor = new CommandExecutor(
            $this->registry,
            new HandlerResolver($this->registry),
            new CommandExecutionCoordinator(new CollectingExecutionReporter()),
            $this->storage,
            new InMemoryCommandExecutionStorage(),
            new SystemClock(),
            new RandomIdentifierGenerator(),
        );

        $this->registry->register(
            new CommandDefinition(CommandName::fromString('demo:run'), handlerKey: 'demo'),
            static fn () => new class () implements CommandHandlerInterface {
                public function handles(): string
                {
                    return 'demo:run';
                }

                public function handle(CommandInterface $command): CommandResultInterface
                {
                    return CommandResult::succeed('ok');
                }
            },
        );
    }

    private function dispatchCommand(string $name): void
    {
        $this->executor->dispatch(new Command(CommandName::fromString($name), new CommandInput()));
    }

    public function testRepeatedDispatchLeavesNoContextOnMainStack(): void
    {
        $this->detector->setCurrent(null);

        for ($i = 0; $i < 50; ++$i) {
            $this->dispatchCommand('demo:run');
            self::assertNull($this->storage->current(), \sprintf('iteration %d: no context leak', $i));
        }
    }

    public function testConcurrentFibersDoNotShareContext(): void
    {
        $fiberA = new Fiber(static fn () => null);
        $fiberB = new Fiber(static fn () => null);

        // Fiber A ambient context.
        $this->detector->setCurrent($fiberA);
        $this->storage->enter(new CommandContext()->with('tenant', 'A', Classification::Internal));

        // Dispatch under A: command context is pushed then popped in finally.
        $this->dispatchCommand('demo:run');

        $afterA = $this->storage->current();
        self::assertNotNull($afterA, 'fiber A keeps its ambient context');
        self::assertSame('A', $afterA->get('tenant'), 'fiber A keeps its ambient context');
        self::assertFalse($afterA->has('command'), 'command context did not leak into ambient');

        // Fiber B sees only its own ambient; A's tenant is invisible.
        $this->detector->setCurrent($fiberB);
        $this->storage->enter(new CommandContext()->with('tenant', 'B', Classification::Internal));

        $this->dispatchCommand('demo:run');

        $afterB = $this->storage->current();
        self::assertNotNull($afterB);
        self::assertSame('B', $afterB->get('tenant'));
        self::assertFalse($afterB->has('command'));

        // Main stack stays clean between requests.
        $this->detector->setCurrent(null);
        self::assertNull($this->storage->current());
    }
}
