<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Exception;

use Codeception\Test\Unit;
use ItPack\RunIt\Domain\Exception\InvalidSecurityLimitException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InvalidSecurityLimitException::class)]
final class InvalidSecurityLimitExceptionTest extends Unit
{
    public function testForNonPositive(): void
    {
        $exception = InvalidSecurityLimitException::forNonPositive('maxAttributeCount', 0);

        self::assertStringContainsString('maxAttributeCount', $exception->getMessage());
        self::assertStringContainsString('0', $exception->getMessage());
    }
}
