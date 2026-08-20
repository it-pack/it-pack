<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSnapshotFactoryInterface;

class ConfigurationSnapshotFactory implements ConfigurationSnapshotFactoryInterface
{
    /**
     * @param array<mixed> $tree
     */
    public function build(
        array $tree
    ): ConfigurationSnapshot {
        return new ConfigurationSnapshot($tree);
    }

    public function buildFromGraph(ConfigurationGraphInterface $graph): ConfigurationSnapshot
    {
        return ConfigurationSnapshot::fromGraph($graph);
    }
}
