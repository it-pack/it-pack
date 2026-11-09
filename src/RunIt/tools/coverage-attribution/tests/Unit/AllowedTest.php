<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[Small]
#[CoversClass(AttributionTarget::class)]
#[UsesClass(AttributionCollaborator::class)]
final class AllowedTest extends TestCase
{
    public function testDeclaredCollaborator(): void
    {
        self::assertSame(2, AttributionCollaborator::execute());
    }
}
