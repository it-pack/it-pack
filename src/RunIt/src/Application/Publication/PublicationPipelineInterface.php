<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;

/**
 * Composes the standard RunIt publication pipeline from its parts.
 *
 * The pipeline is built outside-in so a publish call flows through the stages in
 * the canonical order: guard → fail-safe → filter → redact → sample → buffer → sink.
 * The sink is the only required part; every other stage is optional and skipped when
 * omitted.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface PublicationPipelineInterface
{
    public function build(): ArtifactPublisherInterface;
}
