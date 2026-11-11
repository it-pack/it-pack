<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
#[CoversClass(AttributionTarget::class)]
final class TeardownLeakTest extends TestCase
{
    protected function tearDown(): void
    {
        AttributionCollaborator::execute();
        file_put_contents((string) getenv('ATTRIBUTION_LIFECYCLE_MARKER'), 'teardown');
    }

    public function testTeardownLeak(): void
    {
        self::assertSame(1, AttributionTarget::execute());
    }
}
