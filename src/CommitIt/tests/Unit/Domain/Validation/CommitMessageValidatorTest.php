<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Validation;

use Codeception\Test\Unit;
use ItPack\CommitIt\Domain\Exception\InvalidCommitMessageException;
use ItPack\CommitIt\Domain\Validation\CommitMessageValidator;

/**
 * @covers \ItPack\CommitIt\Domain\Validation\CommitMessageValidator
 */
final class CommitMessageValidatorTest extends Unit
{
    public function testValidMessages(): void
    {
        self::assertNull(CommitMessageValidator::reason('Subject', null));
        self::assertNull(CommitMessageValidator::reason('Subject', 'Body text'));
        self::assertNull(CommitMessageValidator::reason('A reasonably long subject line', "Multi\nline\nbody"));
        self::assertNull(CommitMessageValidator::reason(str_repeat('a', 72), null));

        CommitMessageValidator::assertValid('Subject', 'Body');
    }

    public function testEmptySubjectIsRejected(): void
    {
        self::assertNotNull(CommitMessageValidator::reason('', null));
        self::assertNotNull(CommitMessageValidator::reason('   ', null));
    }

    public function testMultiLineSubjectIsRejected(): void
    {
        self::assertNotNull(CommitMessageValidator::reason("line one\nline two", null));
    }

    public function testOverlongSubjectIsRejected(): void
    {
        self::assertNotNull(CommitMessageValidator::reason(str_repeat('a', 73), null));
    }

    public function testEmptyBodyWhenProvidedIsRejected(): void
    {
        self::assertNotNull(CommitMessageValidator::reason('Subject', ''));
        self::assertNotNull(CommitMessageValidator::reason('Subject', '   '));
    }

    public function testAssertValidThrowsDomainException(): void
    {
        $this->expectException(InvalidCommitMessageException::class);

        CommitMessageValidator::assertValid('', null);
    }
}
