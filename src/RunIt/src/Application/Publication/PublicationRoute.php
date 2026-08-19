<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Application\Publication\ArtifactFilterInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;

/**
 * A single routing rule: a filter and the publisher it targets.
 *
 * The route is an immutable value so routing tables can be built once and shared.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class PublicationRoute
{
    public function __construct(
        public ArtifactFilterInterface $filter,
        public ArtifactPublisherInterface $publisher,
    ) {
    }
}
