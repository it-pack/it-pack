<?php

declare(strict_types=1);

namespace Tests\Unit\SDK;

use Codeception\Test\Unit;
use ItPack\CommandIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\CommandIt\Adapter\Common\SystemClock;
use ItPack\CommandIt\Adapter\Context\AmbientContextStorage;
use ItPack\CommandIt\Adapter\Input\ArrayInputAdapter;
use ItPack\CommandIt\Adapter\Memory\InMemoryCommandExecutionStorage;
use ItPack\CommandIt\Adapter\Reporting\CollectingExecutionReporter;
use ItPack\CommandIt\Contract\Command\CommandHandlerInterface;
use ItPack\CommandIt\Contract\Command\CommandInterface;
use ItPack\CommandIt\Contract\Command\CommandResultInterface;
use ItPack\CommandIt\Contract\Exception\HandlerNotFoundException;
use ItPack\CommandIt\Domain\Argument;
use ItPack\CommandIt\Domain\CommandResult;
use ItPack\CommandIt\Domain\ValueObject\ArgumentName;
use ItPack\CommandIt\SDK\CommandBuilder;
use ItPack\CommandIt\SDK\CommandDefinitionBuilder;
use ItPack\CommandIt\SDK\CommandIt;

/**
 * @covers \ItPack\CommandIt\SDK\CommandIt
 *
 * End-to-end: build a definition with the SDK builder, register it on the
 * facade, parse input with the array adapter, build + dispatch a command, and
 * assert the lifecycle result.
 */
final class CommandItTest extends Unit
{
    private function commandIt(): CommandIt
    {
        return new CommandIt(
            new SystemClock(),
            new RandomIdentifierGenerator(),
            new CollectingExecutionReporter(),
            new AmbientContextStorage(),
            new InMemoryCommandExecutionStorage(),
        );
    }

    private function definition(): \ItPack\CommandIt\Contract\Command\CommandDefinitionInterface
    {
        return new CommandDefinitionBuilder()
            ->name('demo:run')
            ->argument(new Argument(ArgumentName::fromString('count'), required: true, type: 'int'))
            ->handler('demo')
            ->build()
        ;
    }

    public function testRegisterAndDispatchSucceedsWithCoercedInput(): void
    {
        $commandIt = $this->commandIt();
        $definition = $this->definition();

        $commandIt->register($definition, static fn (): CommandHandlerInterface => new class () implements CommandHandlerInterface {
            public function handles(): string
            {
                return 'demo:run';
            }

            public function handle(CommandInterface $command): CommandResultInterface
            {
                return CommandResult::succeed($command->input()->argument('count'));
            }
        });

        $input = new ArrayInputAdapter()->parse($definition, ['count' => '5']);
        $command = new CommandBuilder()->definition($definition)->input($input)->build();

        $result = $commandIt->dispatch($command);

        self::assertSame('succeeded', $result->status()->label());
        self::assertSame(5, $result->payload());
        self::assertNotNull($result->execution());
    }

    public function testDispatchUnknownCommandThrows(): void
    {
        $commandIt = $this->commandIt();

        $this->expectException(HandlerNotFoundException::class);

        $commandIt->dispatch(
            new CommandBuilder()
                ->definition($this->definition())
                ->build(),
        );
    }

    public function testHandlerFailureRethrowsOriginalException(): void
    {
        $commandIt = $this->commandIt();
        $definition = $this->definition();

        $commandIt->register($definition, static fn (): CommandHandlerInterface => new class () implements CommandHandlerInterface {
            public function handles(): string
            {
                return 'demo:run';
            }

            public function handle(CommandInterface $command): CommandResultInterface
            {
                throw new \RuntimeException('boom');
            }
        });

        $input = new ArrayInputAdapter()->parse($definition, ['count' => '1']);
        $command = new CommandBuilder()->definition($definition)->input($input)->build();

        $thrown = null;

        try {
            $commandIt->dispatch($command);
        } catch (\RuntimeException $error) {
            $thrown = $error;
        }

        self::assertNotNull($thrown);
        self::assertSame('boom', $thrown->getMessage());
    }
}
