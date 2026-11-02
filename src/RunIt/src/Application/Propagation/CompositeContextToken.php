<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Propagation;

use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;

/**
 * Pins an attached scope to the sub-storage that owns it.
 *
 * The composite delegates to one of several underlying storages. A foreign or mismatched
 * token must never detach the wrong storage, so the token carries the owning storage and
 * the inner token it issued.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class CompositeContextToken implements ContextTokenInterface
{
    public function __construct(
        public ContextStorageInterface $owner,
        public ContextTokenInterface   $inner,
    ) {
    }

    public function id(): int
    {
        return $this->inner->id();
    }
}
