<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Value;

use Codeception\Test\Unit;
use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\Commit;
use ItPack\CommitIt\Domain\Value\CommitId;
use ItPack\CommitIt\Domain\Value\CommitMessage;
use ItPack\CommitIt\Domain\Value\RemoteName;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\Domain\Value\TagName;
use ItPack\CommitIt\Domain\Value\WorktreeInfo;

/**
 * @covers \ItPack\CommitIt\Domain\Value\RepositoryPath
 * @covers \ItPack\CommitIt\Domain\Value\CommitId
 * @covers \ItPack\CommitIt\Domain\Value\BranchName
 * @covers \ItPack\CommitIt\Domain\Value\TagName
 * @covers \ItPack\CommitIt\Domain\Value\RemoteName
 * @covers \ItPack\CommitIt\Domain\Value\CommitMessage
 * @covers \ItPack\CommitIt\Domain\Value\Commit
 * @covers \ItPack\CommitIt\Domain\Value\WorktreeInfo
 */
final class ValueObjectTest extends Unit
{
    public function testRepositoryPathStoresTrimmedValue(): void
    {
        self::assertSame('/repo', RepositoryPath::from('  /repo  ')->toString());
    }

    public function testValueObjectsRejectEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        RepositoryPath::from('   ');
    }

    public function testCommitIdEqualityAndString(): void
    {
        $a = CommitId::from('abc123');
        $b = CommitId::from('abc123');

        self::assertTrue($a->equals($b));
        self::assertSame('abc123', (string) $a);
    }

    public function testRefNamesExposeValue(): void
    {
        self::assertSame('main', (string) BranchName::from('main'));
        self::assertSame('v1.0', (string) TagName::from('v1.0'));
        self::assertSame('origin', (string) RemoteName::from('origin'));
        self::assertTrue(BranchName::from('main')->equals(BranchName::from('main')));
    }

    public function testCommitMessagePartsAndRoundTrip(): void
    {
        $message = CommitMessage::fromParts('Subject', 'Body text');

        self::assertSame('Subject', $message->subject);
        self::assertSame('Body text', $message->body);
        self::assertSame("Subject\n\nBody text", $message->toString());

        $parsed = CommitMessage::fromString("Subject\n\nBody text");

        self::assertSame('Subject', $parsed->subject);
        self::assertSame('Body text', $parsed->body);
    }

    public function testCommitMessageRejectsEmptySubject(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CommitMessage::fromParts('   ');
    }

    public function testCommitSnapshotIsImmutable(): void
    {
        $commit = new Commit(
            CommitId::from('abc'),
            'Subject',
            null,
            'Jane',
            'jane@example.com',
            new \DateTimeImmutable('2026-01-01 00:00:00'),
            'Jane',
            'jane@example.com',
            new \DateTimeImmutable('2026-01-01 00:00:00'),
        );

        self::assertSame('Subject', $commit->subject);
        self::assertSame('abc', (string) $commit->id);
    }

    public function testWorktreeInfoHoldsState(): void
    {
        $info = new WorktreeInfo('/repo/.worktrees/wt', 'feature', false);

        self::assertSame('/repo/.worktrees/wt', $info->path);
        self::assertSame('feature', $info->branch);
        self::assertFalse($info->isMain);
    }
}
