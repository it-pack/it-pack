<?php

declare(strict_types=1);

namespace ItPack\RunIt\Behavior\Application\Publication;

use ItPack\RunIt\Contract\Port\CloseablePublisherInterface;
use ItPack\RunIt\Contract\Port\FlushablePublisherInterface;

/**
 * Lifecycle delegation shared by every publisher decorator.
 *
 * A decorator wraps an `inner` publisher and must forward flush and close so a
 * buffered, composite, or otherwise stateful sink behind it still drains. The
 * inner publisher is only flushed or closed when it actually supports the
 * operation, so a decorator stays safe in front of a plain
 * `ArtifactPublisherInterface` that is neither flushable nor closeable.
 *
 * The using class must declare `private ArtifactPublisherInterface $inner`
 * (a promoted constructor parameter is fine).
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
trait DelegatesPublisherLifecycleBehavior
{
    public function flush(): void
    {
        if ($this->inner instanceof FlushablePublisherInterface) {
            $this->inner->flush();
        }
    }

    public function close(): void
    {
        if ($this->inner instanceof CloseablePublisherInterface) {
            $this->inner->close();
        }
    }
}
