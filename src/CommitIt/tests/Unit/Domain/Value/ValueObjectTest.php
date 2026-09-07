<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Value;

use Codeception\Test\Unit;
use ItPack\CommitIt\Domain\Exception\InvalidReferenceNameException;
use ItPack\CommitIt\Domain\Exception\InvalidSparsePatternException;
use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\Commit;
use ItPack\CommitIt\Domain\Value\CommitId;
use ItPack\CommitIt\Domain\Value\CommitMessage;
use ItPack\CommitIt\Domain\Value\RemoteName;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\Domain\Value\SparsePattern;
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
 * @covers \ItPack\CommitIt\Domain\Value\SparsePattern
 */
final class ValueObjectTest extends Unit
{
    public function testRepositoryPathStoresTrimmedValue(): void
    {
        self::assertSame('/repo', RepositoryPath::from('  /repo  ')->toString());
    }

    public function testRepositoryPathRejectsControlCharacters(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        RepositoryPath::from("valid\x00path");
    }

    public function testRepositoryPathResolvesRealPath(): void
    {
        $path = RepositoryPath::from('/this/path/does/not/exist');

        // Resolution is deferred and falls back to the original path when absent.
        self::assertSame('/this/path/does/not/exist', $path->realPath);
    }

    public function testValueObjectsRejectEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        RepositoryPath::from('   ');
    }

    public function testRefNamesRejectInvalidInput(): void
    {
        $invalid = ['', '  ', '..', 'a..b', '/head', 'head/', 'a//b', 'a b', 'a~b', 'a^b', 'a:b', 'a?b', 'a*b', 'a[b', 'a\\b', '@{x}', '@', '.hidden', 'a/b/.c', 'a.lock', 'a/b/c.lock'];

        foreach ($invalid as $name) {
            foreach ([BranchName::class, TagName::class, RemoteName::class] as $class) {
                try {
                    $class::from($name);
                    self::fail(sprintf('%s::from() should reject "%s"', $class, $name));
                } catch (InvalidReferenceNameException $e) {
                    self::assertStringContainsString('name is invalid', $e->getMessage());
                }
            }
        }
    }

    public function testRefNamesExposeValue(): void
    {
        self::assertSame('main', (string) BranchName::from('main'));
        self::assertSame('v1.0', (string) TagName::from('v1.0'));
        self::assertSame('origin', (string) RemoteName::from('origin'));
        self::assertTrue(BranchName::from('main')->equals(BranchName::from('main')));
    }

    public function testRefNamesAllowHierarchySeparators(): void
    {
        self::assertSame('feature/login', (string) BranchName::from('feature/login'));
        self::assertSame('release/v1.0', (string) TagName::from('release/v1.0'));
        self::assertSame('upstream/ci', (string) RemoteName::from('upstream/ci'));
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

    public function testCommitMessageRejectsMultiLineSubject(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CommitMessage::fromParts("line one\nline two");
    }

    public function testCommitMessageRejectsOverlongSubject(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CommitMessage::fromParts(str_repeat('a', 73));
    }

    public function testCommitMessageRejectsEmptyBodyWhenProvided(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CommitMessage::fromParts('Subject', '   ');
    }

    public function testCommitMessageTrimsSurroundingWhitespace(): void
    {
        $message = CommitMessage::fromParts('  Subject  ', '  Body text  ');

        self::assertSame('Subject', $message->subject);
        self::assertSame('Body text', $message->body);

        $parsed = CommitMessage::fromString("  Subject  \n\n  Body text  ");

        self::assertSame('Subject', $parsed->subject);
        self::assertSame('Body text', $parsed->body);
    }

    public function testCommitMessagePreservesMultipleParagraphs(): void
    {
        $message = CommitMessage::fromString("Subject\n\nA\n\nB\n\nC");

        self::assertSame('Subject', $message->subject);
        self::assertSame("A\n\nB\n\nC", $message->body);
    }

    public function testCommitIdTrimsWhitespace(): void
    {
        self::assertSame('abc123', CommitId::from('  abc123  ')->__toString());
    }

    public function testCommitIdRejectsWhitespaceOnly(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CommitId::from('   ');
    }

    public function testCommitSnapshotIsImmutable(): void
    {
        $commit = Commit::from(
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

    public function testValueObjectsEqualSameValueAndDifferOtherwise(): void
    {
        self::assertTrue(CommitId::from('abc')->equals(CommitId::from('abc')));
        self::assertFalse(CommitId::from('abc')->equals(CommitId::from('def')));

        self::assertTrue(BranchName::from('main')->equals(BranchName::from('main')));
        self::assertFalse(BranchName::from('main')->equals(BranchName::from('develop')));

        self::assertTrue(TagName::from('v1')->equals(TagName::from('v1')));
        self::assertFalse(TagName::from('v1')->equals(TagName::from('v2')));

        self::assertTrue(RemoteName::from('origin')->equals(RemoteName::from('origin')));
        self::assertFalse(RemoteName::from('origin')->equals(RemoteName::from('upstream')));
    }

    public function testRefNamesAreCaseSensitive(): void
    {
        self::assertFalse(BranchName::from('Main')->equals(BranchName::from('main')));
        self::assertFalse(TagName::from('V1')->equals(TagName::from('v1')));
        self::assertFalse(RemoteName::from('Origin')->equals(RemoteName::from('origin')));
    }

    public function testCommitIdRejectsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CommitId::from('');
    }

    public function testRepositoryPathResolvesExistingDirAndCaches(): void
    {
        $path = RepositoryPath::from(sys_get_temp_dir());

        self::assertSame(realpath(sys_get_temp_dir()), $path->realPath);
        self::assertSame(realpath(sys_get_temp_dir()), $path->realPath, 'second access hits the cached resolution');
        self::assertSame(sys_get_temp_dir(), $path->toString());
        self::assertSame(sys_get_temp_dir(), (string) $path);
    }

    public function testCommitMessageIsStringable(): void
    {
        $message = CommitMessage::fromParts('Subject', 'Body text');

        self::assertSame("Subject\n\nBody text", (string) $message);
    }

    public function testSparsePatternStoresTrimmedValue(): void
    {
        self::assertSame('src/*', SparsePattern::from('  src/*  ')->__toString());
    }

    public function testSparsePatternRejectsEmpty(): void
    {
        $this->expectException(InvalidSparsePatternException::class);

        SparsePattern::from('   ');
    }

    public function testSparsePatternRejectsControlCharacters(): void
    {
        $this->expectException(InvalidSparsePatternException::class);

        SparsePattern::from("src/\x00*");
    }

    public function testSparsePatternRejectsOverlongInput(): void
    {
        $this->expectException(InvalidSparsePatternException::class);

        SparsePattern::from(str_repeat('a', SparsePattern::MAX_LENGTH + 1));
    }

    public function testSparsePatternAcceptsValidPatterns(): void
    {
        $patterns = ['src/', '!src/vendor/*', '*.txt', '/docs/**', 'src/*/public'];

        foreach ($patterns as $pattern) {
            $sparse = SparsePattern::from($pattern);
            self::assertSame($pattern, (string) $sparse);
        }
    }

    public function testSparsePatternEqualsSameValueAndDifferOtherwise(): void
    {
        self::assertTrue(SparsePattern::from('src/*')->equals(SparsePattern::from('src/*')));
        self::assertFalse(SparsePattern::from('src/*')->equals(SparsePattern::from('lib/*')));
    }
}
