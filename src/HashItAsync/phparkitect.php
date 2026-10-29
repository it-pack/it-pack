<?php

declare(strict_types=1);

require_once __DIR__ . '/../../tools/qa/phparkitect/base.php';

use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use Arkitect\RuleBuilders\Architecture\Architecture;

/**
 * phparkitect rules for it-pack/hash-it-async (pilot migration from deptrac).
 *
 * Deptrac layers (src/HashItAsync/deptrac (archived, deleted)):
 *   Bridge   → HashIt, AsyncIt
 *   HashIt   → ~  (depends on nothing — empty in local src/)
 *   AsyncIt  → ~  (depends on nothing — empty in local src/)
 *
 * Translation: the Bridge component may depend on the HashIt and AsyncIt
 * namespace families (consumed externally) and itself; those external
 * components, if they ever appeared locally, would be leaf nodes that depend
 * on nothing.
 */
return static function (Config $config): void {
    $classSet = ClassSet::fromDir(__DIR__ . '/src');

    $architecture = Architecture::withComponents()
        ->component('Bridge')->definedBy('ItPack\HashItAsync\*')
        ->component('HashIt')->definedBy('ItPack\HashIt\*')
        ->component('AsyncIt')->definedBy('ItPack\AsyncIt\*')
        ->where('Bridge')->mayDependOnComponents('HashIt', 'AsyncIt', 'Bridge')
        ->where('HashIt')->mayDependOnComponents('HashIt')
        ->where('AsyncIt')->mayDependOnComponents('AsyncIt');

    $config->add(
        $classSet,
        ...$architecture->rules(),
    );
};
