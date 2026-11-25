<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Exception;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Domain\ActivityStatusInterface;
use ItPack\RunIt\Domain\Activity\ActivityStatus;
use ItPack\RunIt\Domain\Exception\ConflictingActivityCompletionException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ConflictingActivityCompletionException::class)]
final class ConflictingActivityCompletionExceptionTest extends Unit
{
    public function testForConflict(): void
    {
        $existing = $this->createMock(ActivityStatusInterface::class);
        $existing->method('label')->willReturn('succeeded');

        $exception = ConflictingActivityCompletionException::forConflict($existing);

        self::assertStringContainsString('succeeded', $exception->getMessage());
    }

    public function testIsActivityStatus(): void
    {
        self::assertSame('succeeded', ActivityStatus::Succeeded->label());
    }
}
