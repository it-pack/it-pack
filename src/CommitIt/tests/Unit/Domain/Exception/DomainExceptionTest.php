<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Exception;

use Codeception\Test\Unit;
use ItPack\CommitIt\Common\CommitItException;
use ItPack\CommitIt\Contract\Exception\DirtyWorkingTreeException as DirtyWorkingTreeExceptionMarker;
use ItPack\CommitIt\Contract\Exception\InvalidCommitMessageException as InvalidCommitMessageExceptionMarker;
use ItPack\CommitIt\Contract\Exception\InvalidReferenceNameException as InvalidReferenceNameExceptionMarker;
use ItPack\CommitIt\Contract\Exception\RepositoryNotFoundException as RepositoryNotFoundExceptionMarker;
use ItPack\CommitIt\Contract\Exception\ValidationException as ValidationExceptionMarker;
use ItPack\CommitIt\Contract\Exception\VcsOperationException as VcsOperationExceptionMarker;
use ItPack\CommitIt\Domain\Exception\DirtyWorkingTreeException;
use ItPack\CommitIt\Domain\Exception\InvalidCommitMessageException;
use ItPack\CommitIt\Domain\Exception\InvalidReferenceNameException;
use ItPack\CommitIt\Domain\Exception\RepositoryNotFoundException;
use ItPack\CommitIt\Domain\Exception\VcsOperationException;

/**
 * @covers \ItPack\CommitIt\Domain\Exception\VcsOperationException
 * @covers \ItPack\CommitIt\Domain\Exception\RepositoryNotFoundException
 * @covers \ItPack\CommitIt\Domain\Exception\DirtyWorkingTreeException
 * @covers \ItPack\CommitIt\Domain\Exception\InvalidReferenceNameException
 * @covers \ItPack\CommitIt\Domain\Exception\InvalidCommitMessageException
 */
final class DomainExceptionTest extends Unit
{
    public function testExceptionsImplementContractMarkers(): void
    {
        self::assertInstanceOf(VcsOperationExceptionMarker::class, new VcsOperationException());
        self::assertInstanceOf(RepositoryNotFoundExceptionMarker::class, new RepositoryNotFoundException());
        self::assertInstanceOf(DirtyWorkingTreeExceptionMarker::class, new DirtyWorkingTreeException());
        self::assertInstanceOf(InvalidReferenceNameExceptionMarker::class, new InvalidReferenceNameException());
        self::assertInstanceOf(InvalidCommitMessageExceptionMarker::class, new InvalidCommitMessageException());
    }

    public function testExceptionsImplementCommitItMarker(): void
    {
        $exceptions = [
            new VcsOperationException(),
            new RepositoryNotFoundException(),
            new DirtyWorkingTreeException(),
            new InvalidReferenceNameException(),
            new InvalidCommitMessageException(),
        ];

        foreach ($exceptions as $exception) {
            self::assertInstanceOf(CommitItException::class, $exception);
        }
    }

    public function testValidationExceptionsAreInvalidArgument(): void
    {
        self::assertInstanceOf(\InvalidArgumentException::class, new InvalidReferenceNameException());
        self::assertInstanceOf(\InvalidArgumentException::class, new InvalidCommitMessageException());
    }

    public function testValidationExceptionsAreSeparateFromVcsOperation(): void
    {
        self::assertInstanceOf(ValidationExceptionMarker::class, new InvalidReferenceNameException());
        self::assertInstanceOf(ValidationExceptionMarker::class, new InvalidCommitMessageException());

        // Validation failures are pre-backend logic errors; they must not be
        // caught by a `catch (VcsOperationException)` handler.
        self::assertNotInstanceOf(VcsOperationExceptionMarker::class, new InvalidReferenceNameException());
        self::assertNotInstanceOf(VcsOperationExceptionMarker::class, new InvalidCommitMessageException());
    }
}
