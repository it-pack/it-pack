<?php

declare(strict_types=1);

require_once __DIR__ . '/../../tools/qa/phparkitect/base.php';

use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use Arkitect\RuleBuilders\Architecture\Architecture;

/**
 * phparkitect rules for it-pack/file-it (Phase 2.5).
 *
 * FileIt is a leaf micro-lib: a single ValueObject component providing file
 * introspection. It depends on nothing internal and defines a single component
 * (ItPack\FileIt\ValueObject).
 */
return static function (Config $config): void {
    $classSet = ClassSet::fromDir(__DIR__ . '/src');

    $architecture = Architecture::withComponents()
        ->component('ValueObject')->definedBy('ItPack\FileIt\ValueObject\*')
        ->where('ValueObject')->mayDependOnComponents('ValueObject');

    $config->add($classSet, ...$architecture->rules());
};
