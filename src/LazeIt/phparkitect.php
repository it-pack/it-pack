<?php

declare(strict_types=1);

require_once __DIR__ . '/../../tools/qa/phparkitect/base.php';

use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use Arkitect\RuleBuilders\Architecture\Architecture;

/**
 * phparkitect rules for it-pack/laze-it (Phase 2.5).
 *
 * LazeIt is a leaf micro-lib: a single SDK component providing lazy-object
 * utilities. It depends on nothing internal and defines a single component
 * (ItPack\LazeIt\SDK).
 */
return static function (Config $config): void {
    $classSet = ClassSet::fromDir(__DIR__ . '/src');

    $architecture = Architecture::withComponents()
        ->component('SDK')->definedBy('ItPack\LazeIt\SDK\*')
        ->where('SDK')->mayDependOnComponents('SDK');

    $config->add($classSet, ...$architecture->rules());
};
