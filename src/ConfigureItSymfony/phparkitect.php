<?php

declare(strict_types=1);

require_once __DIR__ . '/../../tools/qa/phparkitect/base.php';

use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use Arkitect\RuleBuilders\Architecture\Architecture;

/**
 * phparkitect rules for it-pack/configure-it-symfony (Phase 2.4).
 *
 * ConfigureItSymfony is a Symfony DI bundle adapter over ConfigureIt.
 * The package is a single component that may depend on ConfigureIt core
 * and Symfony components.
 */
return static function (Config $config): void {
    $classSet = ClassSet::fromDir(__DIR__ . '/src');

    $architecture = Architecture::withComponents()
        ->component('Adapter')->definedBy('ItPack\ConfigureItSymfony\*')
        ->where('Adapter')->mayDependOnComponents('Adapter', 'ItPack\ConfigureIt\*');

    $config->add($classSet, ...$architecture->rules());
};
