<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
#[CoversClass(AttributionTarget::class)]
final class AttributionTest extends TestCase
{
    public function testTarget(): void
    {
        self::assertSame(1, AttributionTarget::execute());
    }

    public function testUndeclaredExecution(): void
    {
        self::assertSame(2, AttributionCollaborator::execute());
    }

}
