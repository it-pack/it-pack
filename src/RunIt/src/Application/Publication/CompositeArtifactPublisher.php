<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\CloseablePublisherInterface;
use ItPack\RunIt\Contract\Port\FlushablePublisherInterface;

/**
 * Artifact publisher that fans an artifact out to multiple inner publishers.
 *
 * Every artifact is offered to each inner publisher in insertion order, so
 * composite publication preserves order across sinks. The composite is
 * best-effort: it attempts every sink even when one fails, then surfaces the
 * first failure after all sinks have been attempted. This keeps one broken sink
 * from silently suppressing the others while still reporting that publication
 * did not fully succeed.
 *
 * The composite forwards {@see flush()} and {@see close()} to every inner
 * publisher that supports them, in order.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class CompositeArtifactPublisher implements
    ArtifactPublisherInterface,
    FlushablePublisherInterface,
    CloseablePublisherInterface
{
    /**
     * @param list<ArtifactPublisherInterface> $publishers
     */
    public function __construct(
        private array $publishers,
    ) {
    }

    public function publish(ExecutionArtifactInterface $artifact): void
    {
        $firstFailure = null;

        foreach ($this->publishers as $publisher) {
            try {
                $publisher->publish($artifact);
            } catch (\Throwable $failure) {
                if ($firstFailure === null) {
                    $firstFailure = $failure;
                }
            }
        }

        if ($firstFailure instanceof \Throwable) {
            throw $firstFailure;
        }
    }

    public function flush(): void
    {
        foreach ($this->publishers as $publisher) {
            if ($publisher instanceof FlushablePublisherInterface) {
                $publisher->flush();
            }
        }
    }

    public function close(): void
    {
        foreach ($this->publishers as $publisher) {
            if ($publisher instanceof CloseablePublisherInterface) {
                $publisher->close();
            }
        }
    }
}
