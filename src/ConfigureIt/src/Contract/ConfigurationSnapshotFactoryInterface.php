<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

use ItPack\ConfigureIt\Core\ConfigurationSnapshot;

interface ConfigurationSnapshotFactoryInterface
{
    public function build(
        array $tree
    ): ConfigurationSnapshot;

    public static function buildFromGraph(
        ConfigurationGraphInterface $graph
    ): ConfigurationSnapshot;
}