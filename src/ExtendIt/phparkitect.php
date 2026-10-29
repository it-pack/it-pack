<?php

declare(strict_types=1);

require_once __DIR__ . '/../../tools/qa/phparkitect/base.php';

use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use Arkitect\RuleBuilders\Architecture\Architecture;

/**
 * phparkitect rules for it-pack/extend-it (Phase 2.5).
 *
 * ExtendIt is a leaf micro-lib: a single Inspection component for class
 * extension/generation via Nette PhpGenerator. It depends on
 * ItPack\ExtendIt\Inspection and nette/php-generator.
 */
return static function (Config $config): void {
    $classSet = ClassSet::fromDir(__DIR__ . '/src');

    $architecture = Architecture::withComponents()
        ->component('Inspection')->definedBy('ItPack\ExtendIt\Inspection\*')
        ->where('Inspection')->mayDependOnComponents('Inspection');

    $config->add($classSet, ...$architecture->rules());
};
