<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Exception;

use Codeception\Test\Unit;
use ItPack\RunIt\Domain\Exception\NoActiveExecutionException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(NoActiveExecutionException::class)]
final class NoActiveExecutionExceptionTest extends Unit
{
    public function testInstance(): void
    {
        $exception = NoActiveExecutionException::instance();

        self::assertSame('No execution is currently active.', $exception->getMessage());
    }
}
