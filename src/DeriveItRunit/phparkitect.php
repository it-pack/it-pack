<?php

declare(strict_types=1);

require_once __DIR__ . '/../../tools/qa/phparkitect/base.php';

use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use Arkitect\RuleBuilders\Architecture\Architecture;

/**
 * phparkitect rules for it-pack/derive-it-runit (Phase 2.4).
 *
 * DeriveItRunit is a bridge: DeriveIt telemetry → RunIt execution.
 * It is a sanctioned importer of BOTH DeriveIt and RunIt.
 */
return static function (Config $config): void {
    $classSet = ClassSet::fromDir(__DIR__ . '/src');

    $architecture = Architecture::withComponents()
        ->component('Bridge')->definedBy('ItPack\DeriveItRunit\*')
        ->where('Bridge')->mayDependOnComponents('Bridge', 'ItPack\DeriveIt\*', 'ItPack\RunIt\*');

    $config->add($classSet, ...$architecture->rules());
};
