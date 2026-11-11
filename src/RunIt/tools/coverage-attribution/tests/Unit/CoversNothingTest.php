<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
#[CoversNothing]
final class CoversNothingTest extends TestCase
{
    public function testCoversNothingExecution(): void
    {
        self::assertSame(2, AttributionCollaborator::execute());
        file_put_contents((string) getenv('ATTRIBUTION_LIFECYCLE_MARKER'), 'covers-nothing');
    }
}
