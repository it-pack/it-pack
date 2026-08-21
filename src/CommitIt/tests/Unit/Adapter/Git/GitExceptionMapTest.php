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
    }

    public function testInvalidReferenceMapping(): void
    {
        $exception = new GitException("fatal: unknown revision 'abc'");
        $mapped = GitExceptionMap::map($exception);

        self::assertInstanceOf(InvalidReferenceNameException::class, $mapped);
        self::assertSame($exception, $mapped->getPrevious());
    }

    public function testDirtyTreeMapping(): void
    {
        $exception = new GitException('Committing is not possible because you have uncommitted changes.');
        $mapped = GitExceptionMap::map($exception);

        self::assertInstanceOf(DirtyWorkingTreeException::class, $mapped);
    }

    public function testGenericFallsBackToVcsOperationException(): void
    {
        $exception = new GitException('some unexpected failure');
        $mapped = GitExceptionMap::map($exception);

        self::assertInstanceOf(VcsOperationException::class, $mapped);
    }

    public function testContextIsPrefixed(): void
    {
        $exception = new GitException('boom');
        $mapped = GitExceptionMap::map($exception, 'Doing thing');

        self::assertStringStartsWith('Doing thing: ', $mapped->getMessage());
    }
}
