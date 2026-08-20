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
}
