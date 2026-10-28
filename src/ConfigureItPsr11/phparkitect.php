<?php

declare(strict_types=1);

require_once __DIR__ . '/../../tools/qa/phparkitect/base.php';

use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use Arkitect\RuleBuilders\Architecture\Architecture;

/**
 * phparkitect rules for it-pack/configure-it-psr11 (Phase 2.4).
 *
 * ConfigureItPsr11 is a thin PSR-11 adapter over ConfigureIt's resolved snapshot.
 * The package is a single component that may depend on ConfigureIt core and PSR-11.
 */
return static function (Config $config): void {
    $classSet = ClassSet::fromDir(__DIR__ . '/src');

    $architecture = Architecture::withComponents()
        ->component('Adapter')->definedBy('ItPack\ConfigureItPsr11\*')
        ->where('Adapter')->mayDependOnComponents('Adapter', 'ItPack\ConfigureIt\*');

    $config->add($classSet, ...$architecture->rules());
};
