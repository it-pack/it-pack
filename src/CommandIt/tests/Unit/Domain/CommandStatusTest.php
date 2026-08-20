<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use Codeception\Test\Unit;
use ItPack\CommandIt\Contract\Domain\CommandStatusInterface;
use ItPack\CommandIt\Domain\CommandStatus;

/**
 * @covers \ItPack\CommandIt\Domain\CommandStatus
 */
final class CommandStatusTest extends Unit
{
    public function testLabelsAreStableAndLowercase(): void
    {
        self::assertSame('defined', CommandStatus::Defined->label());
        self::assertSame('succeeded', CommandStatus::Succeeded->label());
    }

    public function testTerminalStatesAreTerminal(): void
    {
        self::assertTrue(CommandStatus::Succeeded->isTerminal());
        self::assertTrue(CommandStatus::Failed->isTerminal());
        self::assertTrue(CommandStatus::Cancelled->isTerminal());
    }

    public function testNonTerminalStatesAreNotTerminal(): void
    {
        self::assertFalse(CommandStatus::Defined->isTerminal());
        self::assertFalse(CommandStatus::Received->isTerminal());
        self::assertFalse(CommandStatus::Executing->isTerminal());
    }

    public function testImplementsContract(): void
    {
        self::assertInstanceOf(CommandStatusInterface::class, CommandStatus::Executing);
    }
}
