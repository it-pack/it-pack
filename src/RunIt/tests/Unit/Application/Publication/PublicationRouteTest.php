<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Publication\PublicationRoute;
use ItPack\RunIt\Contract\Application\Publication\ArtifactFilterInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(PublicationRoute::class)]
final class PublicationRouteTest extends Unit
{
    public function testRouteCanBeCreatedWithFilterAndPublisher(): void
    {
        $filter = $this->createMock(ArtifactFilterInterface::class);
        $publisher = $this->createMock(ArtifactPublisherInterface::class);
        $route = new PublicationRoute($filter, $publisher);

        self::assertSame($filter, $route->filter);
        self::assertSame($publisher, $route->publisher);
    }
}
