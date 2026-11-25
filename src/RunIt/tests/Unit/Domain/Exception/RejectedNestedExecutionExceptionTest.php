<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Exception;

use Codeception\Test\Unit;
use ItPack\RunIt\Domain\Exception\RejectedNestedExecutionException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RejectedNestedExecutionException::class)]
final class RejectedNestedExecutionExceptionTest extends Unit
{
    public function testForExecution(): void
    {
        $exception = RejectedNestedExecutionException::forExecution('exec-1');

        self::assertStringContainsString('exec-1', $exception->getMessage());
        self::assertStringContainsString('already active', $exception->getMessage());
    }
}
