<?php

declare(strict_types=1);

require_once __DIR__ . '/../../tools/qa/phparkitect/base.php';

use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use Arkitect\RuleBuilders\Architecture\Architecture;

/**
 * phparkitect rules for it-pack/configure-it-runit (Phase 2.4).
 *
 * ConfigureItRunit is a bridge: ConfigureIt resolution → RunIt execution.
 * It is a sanctioned importer of BOTH ConfigureIt and RunIt (unlike mirror
 * packages which forbid RunIt imports). Single component depending on both.
 */
return static function (Config $config): void {
    $classSet = ClassSet::fromDir(__DIR__ . '/src');

    $architecture = Architecture::withComponents()
        ->component('Bridge')->definedBy('ItPack\ConfigureItRunit\*')
        ->where('Bridge')->mayDependOnComponents('Bridge', 'ItPack\ConfigureIt\*', 'ItPack\RunIt\*');

    $config->add($classSet, ...$architecture->rules());
};
