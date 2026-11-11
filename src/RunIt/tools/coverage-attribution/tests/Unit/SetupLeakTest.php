<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
#[CoversClass(AttributionTarget::class)]
final class SetupLeakTest extends TestCase
{
    protected function setUp(): void
    {
        AttributionCollaborator::execute();
        file_put_contents((string) getenv('ATTRIBUTION_LIFECYCLE_MARKER'), 'setup');
    }

    public function testSetupLeak(): void
    {
        self::assertSame(1, AttributionTarget::execute());
    }
}
