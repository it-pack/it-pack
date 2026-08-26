<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use Closure;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\CloseablePublisherInterface;
use ItPack\RunIt\Contract\Port\FlushablePublisherInterface;

/**
 * Artifact publisher that forwards every artifact to a callback.
 *
 * Useful for tests and for trivial adapters that only need a side effect. The
 * callback receives the original immutable artifact; it must not retain it.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class CallbackArtifactPublisher implements
    ArtifactPublisherInterface,
    FlushablePublisherInterface,
    CloseablePublisherInterface
{
    /**
     * @param Closure(ExecutionArtifactInterface): void $callback
     */
    public function __construct(
        private Closure  $callback,
        private ?Closure $onFlush = null,
        private ?Closure $onClose = null,
    ) {
    }

    public function publish(ExecutionArtifactInterface $artifact): void
    {
        ($this->callback)($artifact);
    }

    public function flush(): void
    {
        if ($this->onFlush instanceof Closure) {
            ($this->onFlush)();
        }
    }

    public function close(): void
    {
        if ($this->onClose instanceof Closure) {
            ($this->onClose)();
        }
    }
}
