<?php

declare(strict_types=1);

use Codeception\Attribute\DataProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
#[CoversClass(AttributionTarget::class)]
final class ProviderTest extends TestCase
{
    public static function cases(): array
    {
        AttributionCollaborator::execute();

        $marker = getenv('ATTRIBUTION_PROVIDER_MARKER');
        if ($marker !== false && file_put_contents($marker, "provider executed\n") === false) {
            throw new RuntimeException('Could not write provider marker');
        }

        return ['named dataset' => [1]];
    }

    #[DataProvider('cases')]
    public function testProviderDiscovery(int $expected): void
    {
        self::assertSame($expected, AttributionTarget::execute());
    }
}
