<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\CloseablePublisherInterface;
use ItPack\RunIt\Contract\Port\FlushablePublisherInterface;

/**
 * Publisher spy that records flush/close lifecycle calls while swallowing
 * publications, for asserting publisher teardown ordering.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class LifecycleSpy implements ArtifactPublisherInterface, FlushablePublisherInterface, CloseablePublisherInterface
{
    /** @var list<string> */
    public array $calls = [];

    public function publish(ExecutionArtifactInterface $artifact): void
    {
    }

    public function flush(): void
    {
        $this->calls[] = 'flush';
    }

    public function close(): void
    {
        $this->calls[] = 'close';
    }
}
