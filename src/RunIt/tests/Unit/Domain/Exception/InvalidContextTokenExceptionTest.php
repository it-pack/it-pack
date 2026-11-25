<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Exception;

use Codeception\Test\Unit;
use ItPack\RunIt\Domain\Exception\InvalidContextTokenException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InvalidContextTokenException::class)]
final class InvalidContextTokenExceptionTest extends Unit
{
    public function testMismatched(): void
    {
        $exception = InvalidContextTokenException::mismatched();

        self::assertSame('Context token does not belong to this storage.', $exception->getMessage());
    }

    public function testAlreadyDetached(): void
    {
        $exception = InvalidContextTokenException::alreadyDetached();

        self::assertSame('Context token has already been detached.', $exception->getMessage());
    }
}
