<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use Codeception\Test\Unit;
use ItPack\CommandIt\Contract\Exception\CommandExecutionException;
use ItPack\CommandIt\Domain\CommandDefinition;
use ItPack\CommandIt\Domain\CommandExecution;
use ItPack\CommandIt\Domain\CommandResult;
use ItPack\CommandIt\Domain\CommandStatus;
use ItPack\CommandIt\Domain\ValueObject\CommandExecutionId;
use ItPack\CommandIt\Domain\ValueObject\CommandName;

/**
 * @covers \ItPack\CommandIt\Domain\CommandExecution
 * @covers \ItPack\CommandIt\Domain\CommandResult
 */
final class CommandExecutionTest extends Unit
{
    private function definition(): CommandDefinition
    {
        return new CommandDefinition(CommandName::fromString('cache:clear'));
    }

    private function executing(): CommandExecution
    {
        return CommandExecution::define($this->definition(), CommandExecutionId::fromString('exec-1'), 1000)
            ->transition(CommandStatus::Received)
            ->transition(CommandStatus::Executing)
        ;
    }

    public function testDefineStartsInDefinedState(): void
    {
        $execution = CommandExecution::define($this->definition(), CommandExecutionId::fromString('exec-1'), 1000);

        self::assertSame('defined', $execution->status()->label());
        self::assertFalse($execution->isTerminal());
        self::assertSame(1000, $execution->startedAt());
        self::assertNull($execution->endedAt());
    }

    public function testHappyChainReturnsCopies(): void
    {
        $defined = CommandExecution::define($this->definition(), CommandExecutionId::fromString('exec-1'), 1000);
        $received = $defined->transition(CommandStatus::Received);
        $executing = $received->transition(CommandStatus::Executing);
        $done = $executing->complete(CommandResult::succeed(['ok' => true]), 1050);

        self::assertSame('defined', $defined->status()->label(), 'receiver unchanged');
        self::assertSame('received', $received->status()->label());
        self::assertSame('executing', $executing->status()->label());
        self::assertSame('succeeded', $done->status()->label());
        self::assertSame(1050, $done->endedAt());
        self::assertTrue($done->isTerminal());
    }

    public function testCompleteProducesTerminalCopyWithResult(): void
    {
        $result = CommandResult::succeed(['ok' => true]);
        $done = $this->executing()->complete($result, 1050);

        self::assertSame('succeeded', $done->status()->label());
        self::assertTrue($done->isTerminal());
        self::assertSame(1050, $done->endedAt());
        self::assertSame($result, $done->result());
    }

    public function testIllegalTransitionDefinedToExecutingThrows(): void
    {
        $execution = CommandExecution::define($this->definition(), CommandExecutionId::fromString('exec-1'), 1000);

        $this->expectException(CommandExecutionException::class);
        $this->expectExceptionMessage('Illegal transition from "defined" to "executing".');

        $execution->transition(CommandStatus::Executing);
    }

    public function testSelfTransitionReceivedToReceivedThrows(): void
    {
        $execution = CommandExecution::define($this->definition(), CommandExecutionId::fromString('exec-1'), 1000)
            ->transition(CommandStatus::Received)
        ;

        $this->expectException(CommandExecutionException::class);
        $this->expectExceptionMessage('Illegal transition from "received" to "received".');

        $execution->transition(CommandStatus::Received);
    }

    public function testCompleteFromDefinedThrows(): void
    {
        $execution = CommandExecution::define($this->definition(), CommandExecutionId::fromString('exec-1'), 1000);

        $this->expectException(CommandExecutionException::class);
        $this->expectExceptionMessage('Only an executing execution can be completed (current: defined).');

        $execution->complete(CommandResult::succeed(), 1050);
    }

    public function testCompleteFromReceivedThrows(): void
    {
        $execution = CommandExecution::define($this->definition(), CommandExecutionId::fromString('exec-1'), 1000)
            ->transition(CommandStatus::Received)
        ;

        $this->expectException(CommandExecutionException::class);
        $this->expectExceptionMessage('Only an executing execution can be completed (current: received).');

        $execution->complete(CommandResult::succeed(), 1050);
    }

    public function testEndedAtBeforeStartedAtThrows(): void
    {
        $this->expectException(CommandExecutionException::class);
        $this->expectExceptionMessage('Execution ended before it started.');

        $this->executing()->complete(CommandResult::succeed(), 999);
    }

    public function testTerminalExecutionCannotTransitionAgain(): void
    {
        $execution = $this->executing()
            ->complete(CommandResult::succeed(), 1050)
        ;

        $this->expectException(CommandExecutionException::class);

        $execution->transition(CommandStatus::Cancelled);
    }

    public function testTerminalExecutionCannotCompleteAgain(): void
    {
        $execution = $this->executing()
            ->complete(CommandResult::succeed(), 1050)
        ;

        $this->expectException(CommandExecutionException::class);

        $execution->complete(CommandResult::fail(), 1060);
    }
}
