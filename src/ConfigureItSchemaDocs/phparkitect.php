<?php

declare(strict_types=1);

require_once __DIR__ . '/../../tools/qa/phparkitect/base.php';

use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use Arkitect\RuleBuilders\Architecture\Architecture;

/**
 * phparkitect rules for it-pack/configure-it-schema-docs (Phase 2.4).
 *
 * ConfigureItSchemaDocs generates Markdown + JSON-Schema docs from Nette
 * schema definitions. Internal sub-namespaces: Model (data), Renderer (output).
 * Single component: the package may depend on ConfigureIt core. Internal
 * sub-namespace dependencies are not restricted beyond the package boundary.
 */
return static function (Config $config): void {
    $classSet = ClassSet::fromDir(__DIR__ . '/src');

    $architecture = Architecture::withComponents()
        ->component('Adapter')->definedBy('ItPack\ConfigureItSchemaDocs\*')
        ->where('Adapter')->mayDependOnComponents('Adapter', 'ItPack\ConfigureIt\*');

    $config->add($classSet, ...$architecture->rules());
};
