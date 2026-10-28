<?php

declare(strict_types=1);

require_once __DIR__ . '/../../tools/qa/phparkitect/base.php';

use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use Arkitect\RuleBuilders\Architecture\Architecture;

/**
 * phparkitect rules for it-pack/configure-it-testing (Phase 2.4).
 *
 * ConfigureItTesting provides test doubles (fakes/spies) and assertion traits
 * for ConfigureIt consumers. Single component depending on ConfigureIt core.
 */
return static function (Config $config): void {
    $classSet = ClassSet::fromDir(__DIR__ . '/src');

    $architecture = Architecture::withComponents()
        ->component('TestDoubles')->definedBy('ItPack\ConfigureItTesting\*')
        ->where('TestDoubles')->mayDependOnComponents('TestDoubles', 'ItPack\ConfigureIt\*');

    $config->add($classSet, ...$architecture->rules());
};
