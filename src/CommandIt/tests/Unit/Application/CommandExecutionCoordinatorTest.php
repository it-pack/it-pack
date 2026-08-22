<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use Codeception\Test\Unit;
use ItPack\CommandIt\Adapter\Reporting\CollectingExecutionReporter;
use ItPack\CommandIt\Application\CommandExecutionCoordinator;
use ItPack\CommandIt\Domain\CommandDefinition;
use ItPack\CommandIt\Domain\CommandExecution;
use ItPack\CommandIt\Domain\CommandResult;
use ItPack\CommandIt\Domain\ValueObject\CommandExecutionId;
use ItPack\CommandIt\Domain\ValueObject\CommandName;

/**
 * @covers \ItPack\CommandIt\Application\CommandExecutionCoordinator
 */
final class CommandExecutionCoordinatorTest extends Unit
{
    private function definition(): CommandDefinition
    {
        return new CommandDefinition(CommandName::fromString('demo:run'));
    }

    /**
     * @return list<string>
     */
    private function occurrenceNames(CollectingExecutionReporter $reporter): array
    {
        $names = [];
        foreach ($reporter->entries() as $entry) {
            if ($entry['type'] === 'occurrence') {
                $name = $entry['name'];
                if (\is_string($name)) {
                    $names[] = $name;
                }
            }
        }

        return $names;
    }

    public function testBeginTransitionsToReceivedAndEmitsReceivedOccurrence(): void
    {
        $reporter = new CollectingExecutionReporter();
        $coordinator = new CommandExecutionCoordinator($reporter);

        $execution = $coordinator->begin($this->definition(), CommandExecutionId::fromString('e1'), 1000);
        self::assertInstanceOf(CommandExecution::class, $execution);

        self::assertSame('received', $execution->status()->label());
        self::assertContains('command.received.v1', $this->occurrenceNames($reporter));
    }

    public function testSucceedCompletesAsSucceededAndEmitsHandled(): void
    {
        $reporter = new CollectingExecutionReporter();
        $coordinator = new CommandExecutionCoordinator($reporter);

        $started = $coordinator->begin($this->definition(), CommandExecutionId::fromString('e1'), 1000);
        $completed = $coordinator->succeed($started, CommandResult::succeed('payload'), 1010, 10);
        self::assertInstanceOf(CommandExecution::class, $completed);

        self::assertSame('succeeded', $completed->status()->label());
        self::assertContains('command.handled.v1', $this->occurrenceNames($reporter));
        self::assertNotNull($completed->result());
        self::assertSame('payload', $completed->result()->payload());
    }

    public function testFailCompletesAsFailedAndPreservesErrorClass(): void
    {
        $reporter = new CollectingExecutionReporter();
        $coordinator = new CommandExecutionCoordinator($reporter);

        $started = $coordinator->begin($this->definition(), CommandExecutionId::fromString('e1'), 1000);
        $error = new \RuntimeException('boom');
        $completed = $coordinator->fail($started, $error, 1010, 10);
        self::assertInstanceOf(CommandExecution::class, $completed);

        self::assertSame('failed', $completed->status()->label());
        self::assertContains('command.failed.v1', $this->occurrenceNames($reporter));

        $context = null;
        foreach ($reporter->entries() as $entry) {
            if ($entry['type'] === 'occurrence' && $entry['name'] === 'command.failed.v1') {
                $context = (array) $entry['context'];
            }
        }

        self::assertNotNull($context);
        self::assertSame(\RuntimeException::class, $context['error_class']);
        self::assertSame('boom', $context['error_message']);
    }

    public function testMeasurementIsRecordedOnCompletion(): void
    {
        $reporter = new CollectingExecutionReporter();
        $coordinator = new CommandExecutionCoordinator($reporter);

        $started = $coordinator->begin($this->definition(), CommandExecutionId::fromString('e1'), 1000);
        $coordinator->succeed($started, CommandResult::succeed(), 1010, 10);

        $measurements = \array_filter(
            $reporter->entries(),
            static fn (array $e): bool => $e['type'] === 'measurement',
        );
        self::assertNotEmpty($measurements);
    }

    public function testTerminalExecutionCannotBeCompletedAgain(): void
    {
        $coordinator = new CommandExecutionCoordinator(new CollectingExecutionReporter());
        $started = $coordinator->begin($this->definition(), CommandExecutionId::fromString('e1'), 1000);
        $done = $coordinator->succeed($started, CommandResult::succeed(), 1010, 10);

        $this->expectException(\ItPack\CommandIt\Contract\Exception\CommandExecutionException::class);
        $coordinator->fail($done, new \RuntimeException('again'), 1020, 10);
    }
}
