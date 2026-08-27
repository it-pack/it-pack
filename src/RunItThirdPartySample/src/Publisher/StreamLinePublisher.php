<?php

declare(strict_types=1);

namespace ItPack\RunItThirdPartySample\Publisher;

use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;

/**
 * Minimal third-party publisher: it writes one JSON line per artifact to a stream
 * (a resource the consumer already owns) and never retains the artifact.
 *
 * This is the smallest possible implementation of {@see ArtifactPublisherInterface}
 * and is the canonical "hello world" a new adapter author writes.
 */
final class StreamLinePublisher implements ArtifactPublisherInterface
{
    /**
     * @param resource $stream an opened, writable stream (for example `fopen('php://stdout', 'w')`)
     */
    public function __construct(
        private readonly mixed $stream,
    ) {
    }

    public function publish(ExecutionArtifactInterface $artifact): void
    {
        $line = (string) json_encode($artifact->normalize());

        fwrite($this->stream, $line . PHP_EOL);
    }
}
