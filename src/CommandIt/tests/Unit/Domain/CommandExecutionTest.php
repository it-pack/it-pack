<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use Codeception\Test\Unit;
use ItPack\CommandIt\Contract\Exception\CommandExecutionException;
use ItPack\CommandIt\Domain\CommandDefinition;
use ItPack\CommandIt\Domain\CommandExecution;
use ItPack\CommandIt\Domain\CommandResult;
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

    public function testDefineStartsInDefinedState(): void
    {
        $execution = CommandExecution::define($this->definition(), CommandExecutionId::fromString('exec-1'), 1000);

        self::assertSame('defined', $execution->status()->label());
        self::assertFalse($execution->isTerminal());
        self::assertSame(1000, $execution->startedAt());
        self::assertNull($execution->endedAt());
    }

    public function testNonTerminalTransitionReturnsCopy(): void
    {
        $execution = CommandExecution::define($this->definition(), CommandExecutionId::fromString('exec-1'), 1000);
        $running = $execution->transition(\ItPack\CommandIt\Domain\CommandStatus::Executing);

        self::assertSame('executing', $running->status()->label());
        self::assertSame('defined', $execution->status()->label(), 'receiver is unchanged');
    }

    public function testCompleteProducesTerminalCopyWithResult(): void
    {
        $execution = CommandExecution::define($this->definition(), CommandExecutionId::fromString('exec-1'), 1000);
        $result = CommandResult::succeed(['ok' => true]);
        $done = $execution->complete($result, 1050);

        self::assertSame('succeeded', $done->status()->label());
        self::assertTrue($done->isTerminal());
        self::assertSame(1050, $done->endedAt());
        self::assertSame($result, $done->result());
    }

    public function testTerminalExecutionCannotTransitionAgain(): void
    {
        $execution = CommandExecution::define($this->definition(), CommandExecutionId::fromString('exec-1'), 1000)
            ->complete(CommandResult::succeed(), 1050)
        ;

        $this->expectException(CommandExecutionException::class);
        $execution->transition(\ItPack\CommandIt\Domain\CommandStatus::Cancelled);
    }

    public function testTerminalExecutionCannotCompleteAgain(): void
    {
        $execution = CommandExecution::define($this->definition(), CommandExecutionId::fromString('exec-1'), 1000)
            ->complete(CommandResult::succeed(), 1050)
        ;

        $this->expectException(CommandExecutionException::class);
        $execution->complete(CommandResult::fail(), 1060);
    }
}
