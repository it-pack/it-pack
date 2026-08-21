<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

use ItPack\ConfigureIt\Core\ConfigurationSnapshot;

interface ConfigurationSnapshotFactoryInterface
{
    /**
     * @param array<mixed> $tree
     */
    public function build(array $tree): ConfigurationSnapshot;

    public function buildFromGraph(ConfigurationGraphInterface $graph): ConfigurationSnapshot;

    /**
     * Rebuild a snapshot from its serialized {@see \ItPack\ConfigureIt\Core\ConfigurationSnapshot::toArray()} form.
     *
     * @param array<array-key, mixed> $data
     */
    public function buildFromArray(array $data): ConfigurationSnapshot;
}
