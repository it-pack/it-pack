<?php

declare(strict_types=1);

namespace ItPack\ConfigureItPsr11;

use ItPack\ConfigureIt\Contract\ConfigurationSnapshotInterface;
use ItPack\ConfigureItPsr11\Exception\EntryNotFoundException;
use Psr\Container\ContainerInterface;

/**
 * A minimal PSR-11 container whose only job is to expose the resolved
 * ConfigureIt configuration snapshot.
 *
 * The snapshot is registered under a single, stable entry id — by default the
 * {@see ConfigurationSnapshotInterface} class name, so any PSR-11 aware
 * consumer can fetch it without knowing ConfigureIt specifics:
 *
 * ```php
 * $snapshot = $container->get(ConfigurationSnapshotInterface::class);
 * ```
 *
 * The container is intentionally closed: it does not build or cache anything
 * beyond the snapshot it was given. Construction (and thus resolution) is the
 * caller's responsibility, keeping this adapter free of any resolution policy.
 */
final readonly class ConfigureItContainer implements ContainerInterface
{
    /**
     * @param ConfigurationSnapshotInterface $snapshot the resolved snapshot to serve.
     * @param string                         $entryId  the container key under which the snapshot is exposed.
     */
    public function __construct(
        private ConfigurationSnapshotInterface $snapshot,
        private string                         $entryId = ConfigurationSnapshotInterface::class,
    ) {
    }

    public function get(string $id): mixed
    {
        if ($id === $this->entryId) {
            return $this->snapshot;
        }

        throw new EntryNotFoundException(\sprintf('Container entry "%s" is not provided by ConfigureIt.', $id));
    }

    public function has(string $id): bool
    {
        return $id === $this->entryId;
    }
}
