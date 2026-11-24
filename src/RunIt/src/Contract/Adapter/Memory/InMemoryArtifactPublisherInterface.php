<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Adapter\Memory;

use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;

/**
 * Contract for an in-memory artifact publisher that retains published artifacts.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface InMemoryArtifactPublisherInterface
{
    /**
     * @return list<ExecutionArtifactInterface>
     */
    public function published(): array;
}
