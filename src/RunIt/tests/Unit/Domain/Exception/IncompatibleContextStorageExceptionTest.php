<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Exception;

use Codeception\Test\Unit;
use ItPack\RunIt\Domain\Exception\IncompatibleContextStorageException;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IncompatibleContextStorageException::class)]
final class IncompatibleContextStorageExceptionTest extends Unit
{
    public function testSharedStackWithConcurrentEngine(): void
    {
        $exception = IncompatibleContextStorageException::sharedStackWithConcurrentEngine();

        self::assertStringContainsString('StackContextStorage', $exception->getMessage());
        self::assertInstanceOf(LogicException::class, $exception);
    }
}
