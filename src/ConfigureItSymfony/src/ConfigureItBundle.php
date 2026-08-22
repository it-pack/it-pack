<?php

declare(strict_types=1);

namespace ItPack\ConfigureItSymfony;

use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony bundle for ConfigureIt.
 *
 * Registers {@see ConfigureItExtension}, which wires the resolved
 * {@see \ItPack\ConfigureIt\Contract\ConfigurationSnapshotInterface} (and the
 * configured {@see \ItPack\ConfigureIt\Core\ConfigurationBuilder}) into the
 * container. Drop it into `config/bundles.php` like any other bundle:
 *
 * ```php
 * ItPack\ConfigureItSymfony\ConfigureItBundle::class => ['all' => true],
 * ```
 */
final class ConfigureItBundle extends Bundle
{
    public function getContainerExtension(): ExtensionInterface
    {
        return new ConfigureItExtension();
    }
}
