<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class NoMetadataTest extends TestCase
{
    public function testMissingMetadata(): void
    {
        self::assertSame(2, AttributionCollaborator::execute());
    }
}
