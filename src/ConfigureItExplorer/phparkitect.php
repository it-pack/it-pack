<?php

declare(strict_types=1);

require_once __DIR__ . '/../../tools/qa/phparkitect/base.php';

use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use Arkitect\RuleBuilders\Architecture\Architecture;

/**
 * phparkitect rules for it-pack/configure-it-explorer (Phase 2.4).
 *
 * ConfigureItExplorer is a read-only web explorer + graph query API over
 * ConfigureIt's resolved configuration. Internal sub-namespaces: Graph, Http,
 * Query, Server, View. Single component: the package may depend on
 * ConfigureIt core. Internal sub-namespace layering is not yet enforced.
 */
return static function (Config $config): void {
    $classSet = ClassSet::fromDir(__DIR__ . '/src');

    $architecture = Architecture::withComponents()
        ->component('Adapter')->definedBy('ItPack\ConfigureItExplorer\*')
        ->where('Adapter')->mayDependOnComponents('Adapter', 'ItPack\ConfigureIt\*');

    $config->add($classSet, ...$architecture->rules());
};
