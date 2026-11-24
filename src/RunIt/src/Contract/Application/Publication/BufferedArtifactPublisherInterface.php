<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application\Publication;

/**
 * Contract for a buffered artifact publisher that tracks buffered count.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface BufferedArtifactPublisherInterface
{
    public function bufferedCount(): int;

    public function droppedCount(): int;

    public function estimatedBytes(): int;
}
