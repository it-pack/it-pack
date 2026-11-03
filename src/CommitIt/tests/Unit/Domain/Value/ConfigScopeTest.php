<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Value;

use Codeception\Test\Unit;
use ItPack\CommitIt\Domain\Value\ConfigScope;
use ValueError;

/**
 * @covers \ItPack\CommitIt\Domain\Value\ConfigScope
 */
final class ConfigScopeTest extends Unit
{
    public function testAllScopesHaveValues(): void
    {
        self::assertSame('system', ConfigScope::System->value);
        self::assertSame('global', ConfigScope::Global->value);
        self::assertSame('local', ConfigScope::Local->value);
        self::assertSame('worktree', ConfigScope::Worktree->value);
        self::assertSame('file', ConfigScope::File->value);
    }

    public function testFromResolvesValidScope(): void
    {
        self::assertSame(ConfigScope::Local, ConfigScope::from('local'));
        self::assertSame(ConfigScope::Global, ConfigScope::from('global'));
        self::assertSame(ConfigScope::System, ConfigScope::from('system'));
        self::assertSame(ConfigScope::Worktree, ConfigScope::from('worktree'));
        self::assertSame(ConfigScope::File, ConfigScope::from('file'));
    }

    public function testFromRejectsInvalidScope(): void
    {
        $this->expectException(ValueError::class);

        ConfigScope::from('invalid');
    }

    public function testCasesAreExhaustive(): void
    {
        $cases = ConfigScope::cases();

        self::assertCount(5, $cases);
    }
}
