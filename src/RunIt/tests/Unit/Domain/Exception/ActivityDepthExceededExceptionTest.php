<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Exception;

use Codeception\Test\Unit;
use ItPack\RunIt\Domain\Exception\ActivityDepthExceededException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ActivityDepthExceededException::class)]
final class ActivityDepthExceededExceptionTest extends Unit
{
    public function testForDepth(): void
    {
        $exception = ActivityDepthExceededException::forDepth(10, 5);

        self::assertStringContainsString('5', $exception->getMessage());
        self::assertStringContainsString('10', $exception->getMessage());
    }
}
