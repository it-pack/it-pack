<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Psr\Event;

use ItPack\RunIt\Contract\Application\Publication\PublicationFailurePolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

/**
 * Publishes RunIt occurrences through a PSR-14 event dispatcher.
 *
 * Each occurrence is wrapped in an {@see OccurrenceEvent} (preserving the
 * occurrence's immutability) and handed to the dispatcher. Listeners receive the
 * event, may read the occurrence, and may stop propagation without altering the
 * occurrence itself.
 *
 * If dispatch throws, the configured {@see PublicationFailurePolicyInterface} is
 * applied (for example fail-open to swallow, or rethrow under fail-closed). When
 * no policy is configured the publisher rethrows so the caller observes the
 * dispatcher failure; in a RunIt publication pipeline the pipeline already wraps
 * the publisher in a fail-safe guard.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Occurrence
 */
final readonly class OccurrencePublisher implements ArtifactPublisherInterface
{
    public function __construct(
        private EventDispatcherInterface           $dispatcher,
        private ?PublicationFailurePolicyInterface $failurePolicy = null,
        private ?OccurrenceEventWrapper            $wrapper = null,
    ) {
    }

    public function publish(ExecutionArtifactInterface $artifact): void
    {
        if (!$artifact instanceof OccurrenceInterface) {
            return;
        }

        $event = ($this->wrapper ?? new OccurrenceEventWrapper())->wrap($artifact);

        try {
            $this->dispatcher->dispatch($event);
        } catch (Throwable $throwable) {
            if ($this->failurePolicy instanceof PublicationFailurePolicyInterface) {
                $this->failurePolicy->handle($throwable, $artifact);

                return;
            }

            throw $throwable;
        }
    }
}
