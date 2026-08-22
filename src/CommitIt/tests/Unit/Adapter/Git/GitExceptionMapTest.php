<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Git;

use Codeception\Test\Unit;
use CzProject\GitPhp\GitException;
use ItPack\CommitIt\Adapter\Git\GitExceptionMap;
use ItPack\CommitIt\Domain\Exception\DirtyWorkingTreeException;
use ItPack\CommitIt\Domain\Exception\InvalidReferenceNameException;
use ItPack\CommitIt\Domain\Exception\RepositoryNotFoundException;
use ItPack\CommitIt\Domain\Exception\VcsOperationException;

/**
 * @covers \ItPack\CommitIt\Adapter\Git\GitExceptionMap
 */
final class GitExceptionMapTest extends Unit
{
    public function testRepositoryNotFoundMapping(): void
    {
        $exception = new GitException("Repository 'x' not found.");
        $mapped = GitExceptionMap::map($exception);

        self::assertInstanceOf(RepositoryNotFoundException::class, $mapped);
        self::assertSame($exception, $mapped->getPrevious());
        self::assertSame(0, $mapped->getCode());
    }

    public function testInvalidReferenceMapping(): void
    {
        $exception = new GitException("fatal: unknown revision 'abc'");
        $mapped = GitExceptionMap::map($exception);

        self::assertInstanceOf(InvalidReferenceNameException::class, $mapped);
        self::assertSame($exception, $mapped->getPrevious());
        self::assertSame(0, $mapped->getCode());
    }

    public function testDirtyTreeMapping(): void
    {
        $exception = new GitException('Committing is not possible because you have uncommitted changes.');
        $mapped = GitExceptionMap::map($exception);

        self::assertInstanceOf(DirtyWorkingTreeException::class, $mapped);
        self::assertSame(0, $mapped->getCode());
    }

    public function testGenericFallsBackToVcsOperationException(): void
    {
        $exception = new GitException('some unexpected failure');
        $mapped = GitExceptionMap::map($exception);

        self::assertInstanceOf(VcsOperationException::class, $mapped);
        self::assertSame(0, $mapped->getCode());
        self::assertSame('some unexpected failure', $mapped->getMessage());
    }

    public function testContextIsPrefixed(): void
    {
        $exception = new GitException('boom');
        $mapped = GitExceptionMap::map($exception, 'Doing thing');

        self::assertSame('Doing thing: boom', $mapped->getMessage());
    }

    public function testMappingIsCaseInsensitive(): void
    {
        $exception = new GitException("REPOSITORY 'X' NOT FOUND.");
        $mapped = GitExceptionMap::map($exception);

        self::assertInstanceOf(RepositoryNotFoundException::class, $mapped);
    }

    /**
     * Every keyword in the three category arrays must map to its intended
     * exception type. This kills `ArrayItemRemoval` mutants: dropping a keyword
     * from its category makes the dedicated message fall through to a different
     * (or generic) classification, which these assertions reject.
     *
     * @dataProvider keywordProvider
     * @param class-string $expectedType
     */
    public function testEachKeywordMapsToExpectedType(string $message, string $expectedType): void
    {
        $exception = new GitException($message);
        $mapped = GitExceptionMap::map($exception);

        self::assertInstanceOf($expectedType, $mapped);
    }

    /**
     * @return iterable<array{0: string, 1: class-string}>
     */
    public static function keywordProvider(): iterable
    {
        $cases = [
            RepositoryNotFoundException::class => [
                'not found',
                'not a git repository',
                'not a valid git repository',
                'does not exist',
                'no such file or directory',
            ],
            InvalidReferenceNameException::class => [
                'not a valid',
                'unknown revision',
                'ambiguous',
                'malformed',
                'invalid reference',
                'bad revision',
                'fatal: bad',
                'does not match',
            ],
            DirtyWorkingTreeException::class => [
                'working tree',
                'working directory',
                'uncommitted',
                'not clean',
                'local changes',
                'would be overwritten',
                'please commit',
                'cannot ',
            ],
        ];

        foreach ($cases as $expectedType => $keywords) {
            foreach ($keywords as $keyword) {
                yield $keyword => [$keyword, $expectedType];
            }
        }
    }
}
