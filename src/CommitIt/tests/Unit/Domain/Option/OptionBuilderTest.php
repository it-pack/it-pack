<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Option;

use Codeception\Test\Unit;
use ItPack\CommitIt\Domain\Option\CloneOptions;
use ItPack\CommitIt\Domain\Option\CommitOptions;
use ItPack\CommitIt\Domain\Option\InitOptions;
use ItPack\CommitIt\Domain\Value\ConfigScope;

/**
 * @covers \ItPack\CommitIt\Domain\Option\InitOptions
 * @covers \ItPack\CommitIt\Domain\Option\CloneOptions
 * @covers \ItPack\CommitIt\Domain\Option\CommitOptions
 */
final class OptionBuilderTest extends Unit
{
    public function testInitOptionsAreImmutable(): void
    {
        $base = InitOptions::default();

        self::assertNull($base->initialBranch);
        self::assertFalse($base->bare);

        $withBranch = $base->withInitialBranch('main');

        self::assertSame('main', $withBranch->initialBranch);
        self::assertNull($base->initialBranch, 'original must stay unchanged');

        $withBare = $withBranch->withBare();

        self::assertTrue($withBare->bare);
        self::assertFalse($withBranch->bare, 'original must stay unchanged');
    }

    public function testCloneOptionsFluency(): void
    {
        $options = CloneOptions::default()
            ->withBranch('main')
            ->withDepth(1)
        ;

        self::assertSame('main', $options->branch);
        self::assertSame(1, $options->depth);
        self::assertFalse($options->bare);
    }

    public function testCommitOptionsFluency(): void
    {
        $date = new \DateTimeImmutable('2026-01-01 00:00:00');

        $options = CommitOptions::default()
            ->withAuthor('Jane', 'jane@example.com')
            ->withAuthorDate($date)
            ->withAmend()
        ;

        self::assertSame('Jane', $options->authorName);
        self::assertSame('jane@example.com', $options->authorEmail);
        self::assertSame($date, $options->authorDate);
        self::assertTrue($options->amend);
        self::assertFalse($options->allowEmpty);
    }

    public function testCloneOptionsBareFlag(): void
    {
        $options = CloneOptions::default()->withBare();

        self::assertTrue($options->bare);
        self::assertNull($options->branch);
        self::assertNull($options->depth);
    }

    public function testCommitOptionsAllowEmptyFlag(): void
    {
        $options = CommitOptions::default()->withAllowEmpty();

        self::assertTrue($options->allowEmpty);
        self::assertFalse($options->amend);
        self::assertNull($options->authorName);
    }

    public function testInitOptionsWithConfig(): void
    {
        $base = InitOptions::default();

        self::assertSame([], $base->config);

        $withConfig = $base->withConfig(
            ['user.name' => 'Alice', 'user.email' => 'alice@example.com'],
            ConfigScope::Local,
        );

        self::assertSame(
            ['user.name' => 'Alice', 'user.email' => 'alice@example.com'],
            $withConfig->config['local'],
        );
        self::assertSame([], $base->config, 'original must stay unchanged');
    }

    public function testInitOptionsWithConfigGlobalScope(): void
    {
        $options = InitOptions::default()->withConfig(
            ['core.editor' => 'vim'],
            ConfigScope::Global,
        );

        self::assertSame(['core.editor' => 'vim'], $options->config['global']);
    }

    public function testInitOptionsMultipleScopes(): void
    {
        $options = InitOptions::default()
            ->withConfig(['user.name' => 'Alice'], ConfigScope::Local)
            ->withConfig(['core.editor' => 'vim'], ConfigScope::Global)
        ;

        self::assertSame(['user.name' => 'Alice'], $options->config['local']);
        self::assertSame(['core.editor' => 'vim'], $options->config['global']);
    }

    public function testCloneOptionsWithConfig(): void
    {
        $base = CloneOptions::default();

        self::assertSame([], $base->config);

        $withConfig = $base->withConfig(
            ['user.name' => 'Bob'],
            ConfigScope::Local,
        );

        self::assertSame(['user.name' => 'Bob'], $withConfig->config['local']);
        self::assertSame([], $base->config, 'original must stay unchanged');
    }

    public function testCloneOptionsConfigWithOtherOptions(): void
    {
        $options = CloneOptions::default()
            ->withBranch('main')
            ->withDepth(1)
            ->withConfig(['user.email' => 'bob@example.com'], ConfigScope::Local)
        ;

        self::assertSame('main', $options->branch);
        self::assertSame(1, $options->depth);
        self::assertSame(['user.email' => 'bob@example.com'], $options->config['local']);
    }
}
