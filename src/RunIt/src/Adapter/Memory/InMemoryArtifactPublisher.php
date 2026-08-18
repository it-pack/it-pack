<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Memory;

use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;

/**
 * In-memory artifact publisher for tests, examples, and disabled emission.
 *
 * It retains every published artifact in insertion order so callers can assert on what was
 * emitted. It never throws, so it is also a safe default when publication is intentionally a
 * no-op from the consumer's perspective.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class InMemoryArtifactPublisher implements ArtifactPublisherInterface
{
    /**
     * @var list<ExecutionArtifactInterface>
     */
    private array $artifacts = [];

    public function publish(ExecutionArtifactInterface $artifact): void
    {
        $this->artifacts[] = $artifact;
    }

    /**
     * @return list<ExecutionArtifactInterface>
     */
    public function published(): array
    {
        return $this->artifacts;
    }
}
