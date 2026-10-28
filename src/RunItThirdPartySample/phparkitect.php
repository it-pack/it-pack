<?php

declare(strict_types=1);

require_once __DIR__ . '/../../tools/qa/phparkitect/base.php';

use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use Arkitect\RuleBuilders\Architecture\Architecture;

/**
 * phparkitect rules for it-pack/run-it-third-party-sample (Phase 2.4).
 *
 * RunItThirdPartySample is a third-party conformance reference adapter for
 * RunIt's ContextStorageInterface and ArtifactPublisherInterface ports.
 * Internal sub-namespaces: Context (storage adapter), Publisher (artifact adapter).
 * Single component: the package may depend on RunIt core.
 */
return static function (Config $config): void {
    $classSet = ClassSet::fromDir(__DIR__ . '/src');

    $architecture = Architecture::withComponents()
        ->component('Adapter')->definedBy('ItPack\RunItThirdPartySample\*')
        ->where('Adapter')->mayDependOnComponents('Adapter', 'ItPack\RunIt\*');

    $config->add($classSet, ...$architecture->rules());
};
