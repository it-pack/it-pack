<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Option;

use Codeception\Test\Unit;
use ItPack\CommitIt\Domain\Option\CloneOptions;
use ItPack\CommitIt\Domain\Option\CommitOptions;
use ItPack\CommitIt\Domain\Option\InitOptions;

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
}
