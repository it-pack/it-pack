<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Exception;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Domain\ExecutionStatusInterface;
use ItPack\RunIt\Domain\Exception\ConflictingCompletionException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ConflictingCompletionException::class)]
final class ConflictingCompletionExceptionTest extends Unit
{
    public function testForConflict(): void
    {
        $existing = $this->createMock(ExecutionStatusInterface::class);
        $existing->method('label')->willReturn('failed');
        $attempted = $this->createMock(ExecutionStatusInterface::class);
        $attempted->method('label')->willReturn('succeeded');

        $exception = ConflictingCompletionException::forConflict($existing, $attempted);

        self::assertStringContainsString('failed', $exception->getMessage());
        self::assertStringContainsString('succeeded', $exception->getMessage());
    }
}
