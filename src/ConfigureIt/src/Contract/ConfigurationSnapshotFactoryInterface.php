<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

interface ConfigurationSnapshotFactoryInterface
{
    /**
     * @param array<mixed> $tree
     */
    public function build(array $tree): ConfigurationSnapshotInterface;

    public function buildFromGraph(ConfigurationGraphInterface $graph): ConfigurationSnapshotInterface;

    /**
     * Rebuild a snapshot from its serialized {@see ConfigurationSnapshotInterface} form.
     *
     * @param array<array-key, mixed> $data
     */
    public function buildFromArray(array $data): ConfigurationSnapshotInterface;
}
