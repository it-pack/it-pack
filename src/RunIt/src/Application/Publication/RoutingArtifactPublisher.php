<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\CloseablePublisherInterface;
use ItPack\RunIt\Contract\Port\FlushablePublisherInterface;

/**
 * Artifact publisher that routes each artifact to the publishers whose filter accepts it.
 *
 * An artifact may match several routes and is published to each, in route order, so
 * routing fans out as well as splits. When no route accepts an artifact it goes to an
 * optional fallback publisher (for example a dead-letter or debug sink). Routing runs
 * before expensive adapter mapping, so unrouted artifacts cost nothing downstream.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class RoutingArtifactPublisher implements ArtifactPublisherInterface, FlushablePublisherInterface, CloseablePublisherInterface
{
    /**
     * @param list<PublicationRoute>          $routes
     * @param ?ArtifactPublisherInterface     $fallback
     */
    public function __construct(
        private array                       $routes,
        private ?ArtifactPublisherInterface $fallback = null,
    ) {
    }

    public function publish(ExecutionArtifactInterface $artifact): void
    {
        $matched = false;

        foreach ($this->routes as $route) {
            if ($route->filter->accepts($artifact)) {
                $route->publisher->publish($artifact);
                $matched = true;
            }
        }

        if (!$matched && $this->fallback instanceof ArtifactPublisherInterface) {
            $this->fallback->publish($artifact);
        }
    }

    public function flush(): void
    {
        foreach ($this->routes as $route) {
            if ($route->publisher instanceof FlushablePublisherInterface) {
                $route->publisher->flush();
            }
        }

        if ($this->fallback instanceof FlushablePublisherInterface) {
            $this->fallback->flush();
        }
    }

    public function close(): void
    {
        foreach ($this->routes as $route) {
            if ($route->publisher instanceof CloseablePublisherInterface) {
                $route->publisher->close();
            }
        }

        if ($this->fallback instanceof CloseablePublisherInterface) {
            $this->fallback->close();
        }
    }
}
